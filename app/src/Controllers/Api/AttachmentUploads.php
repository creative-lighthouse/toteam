<?php

namespace App\Controllers\Api;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\ORM\DataObject;
use SilverStripe\Model\List\SS_List;

/**
 * Bilder und Dokumente an Datensätzen (Inventar-Objekte, Räume): Upload mit
 * Typ-/Größenprüfung per Mime-Type sowie einheitliche Ausgabe fürs Frontend.
 * Erwartet many_many-Relationen "Images" (Image) und "Documents" (File).
 */
trait AttachmentUploads
{
    protected const ATTACHMENT_MAX_SIZE = 10 * 1024 * 1024;

    protected const ATTACHMENT_IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    protected const ATTACHMENT_DOCUMENT_MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    protected function attachmentSlug(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value !== '' ? $value : 'Unbenannt';
    }

    /**
     * Normalisiert `$_FILES[$key]` (einzelne Datei oder `name[]`-Array) zu einer Liste.
     */
    protected function uploadedFiles(string $key): array
    {
        $raw = $_FILES[$key] ?? null;
        if (!$raw) {
            return [];
        }
        if (!is_array($raw['name'])) {
            return $raw['error'] === UPLOAD_ERR_NO_FILE ? [] : [$raw];
        }
        $files = [];
        foreach ($raw['name'] as $i => $name) {
            if ($raw['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => $name,
                'type'     => $raw['type'][$i],
                'tmp_name' => $raw['tmp_name'][$i],
                'error'    => $raw['error'][$i],
                'size'     => $raw['size'][$i],
            ];
        }
        return $files;
    }

    /**
     * Speichert eine hochgeladene Datei im Ordner $folder.
     *
     * @param array<string, string> $allowedMimes Mime => Endung
     * @return array{success: bool, file?: File, error?: string}
     */
    protected function storeAttachment(array $file, string $folder, string $class, array $allowedMimes): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Datei "' . $file['name'] . '" konnte nicht hochgeladen werden'];
        }
        if ($file['size'] > self::ATTACHMENT_MAX_SIZE) {
            return ['success' => false, 'error' => 'Die Datei "' . $file['name'] . '" ist größer als 10 MB'];
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowedMimes[$mime])) {
            return ['success' => false, 'error' => 'Dateityp von "' . $file['name'] . '" ist nicht erlaubt'];
        }

        $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
        $filename = $this->attachmentSlug($baseName) . '-' . substr(md5(uniqid('', true)), 0, 6) . '.' . $allowedMimes[$mime];

        /** @var File $record */
        $record = $class::create();
        $upload = Upload::create();
        $upload->getValidator()->setAllowedExtensions(array_values(array_unique($allowedMimes)));
        $upload->getValidator()->setAllowedMaxFileSize(self::ATTACHMENT_MAX_SIZE);

        $uploaded = $upload->loadIntoFile([
            'name'     => $filename,
            'type'     => $mime,
            'tmp_name' => $file['tmp_name'],
            'error'    => UPLOAD_ERR_OK,
            'size'     => $file['size'],
        ], $record, $folder);

        if (!$uploaded) {
            $errors = $upload->getErrors();
            return ['success' => false, 'error' => !empty($errors) ? implode(', ', $errors) : 'Datei konnte nicht gespeichert werden'];
        }

        $record->Title = $baseName;
        $record->write();
        $record->publishSingle();

        return ['success' => true, 'file' => $record];
    }

    /**
     * Speichert die hochgeladenen `images[]`/`documents[]` einmal und hängt sie an
     * alle $targets (z.B. alle gleichen Objekte einer Gruppe).
     *
     * @param DataObject[] $targets
     * @return string[] Fehlermeldungen
     */
    protected function attachUploadedFiles(array $targets, string $folder): array
    {
        $errors = [];
        $kinds = [
            'images'    => ['Images', Image::class, self::ATTACHMENT_IMAGE_MIMES],
            'documents' => ['Documents', File::class, self::ATTACHMENT_DOCUMENT_MIMES],
        ];
        foreach ($kinds as $key => [$relation, $class, $mimes]) {
            foreach ($this->uploadedFiles($key) as $file) {
                $result = $this->storeAttachment($file, $folder, $class, $mimes);
                if (!$result['success']) {
                    $errors[] = $result['error'];
                    continue;
                }
                foreach ($targets as $target) {
                    $target->$relation()->add($result['file']);
                }
            }
        }
        return $errors;
    }

    /** Bilder und Dokumente eines Datensatzes fürs Frontend */
    protected function formatAttachments(DataObject $record): array
    {
        return [
            'Images'    => $this->formatImages($record->Images()),
            'Documents' => $this->formatDocuments($record->Documents()),
        ];
    }

    protected function formatImages(SS_List $images): array
    {
        $data = [];
        foreach ($images as $image) {
            $data[] = [
                'ID'        => $image->ID,
                'URL'       => $image->getURL(),
                'Thumbnail' => $image->Fill(300, 300)->getURL(),
                'Name'      => $image->Name,
            ];
        }
        return $data;
    }

    protected function formatDocuments(SS_List $documents): array
    {
        $data = [];
        foreach ($documents as $document) {
            $data[] = [
                'ID'   => $document->ID,
                'URL'  => $document->getURL(),
                'Name' => $document->Title ?: $document->Name,
                'Size' => $document->getAbsoluteSize(),
            ];
        }
        return $data;
    }
}
