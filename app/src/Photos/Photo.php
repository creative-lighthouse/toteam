<?php

namespace App\Photos;

use App\Skript\ScriptRole;
use App\Teams\Organization;
use App\Teams\OrgEvent;
use App\Teams\OrgPermissions;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Photos\Photo
 *
 * Ein Foto im Foto-Totem. Die Datei selbst liegt im Bunny Storage der Organisation
 * ({@see PhotoStorageConfig}) — hier stehen nur die Metadaten. Hochgeladen werden zwei
 * Fassungen, die der Browser vorher selbst verkleinert: `StoragePath` (Ansicht, max.
 * 2560px) und `ThumbPath` (Vorschau). Das Original wird nicht gespeichert.
 *
 * Getaggt wird nach Personen (`People`, Gäste eingeschlossen), Charakteren (`Characters` =
 * Skript-Rollen der Organisation) und freien Schlagworten (`Tags`). Über `Events` gehört
 * ein Foto zu beliebig vielen Events der Organisation.
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $Copyright
 * @property ?string $StoragePath
 * @property ?string $ThumbPath
 * @property ?string $OriginalName
 * @property ?string $Hash
 * @property int $Width
 * @property int $Height
 * @property int $FileSize
 * @property int $ThumbSize
 * @property ?string $TakenAt
 * @property ?float $Latitude
 * @property ?float $Longitude
 * @property int $OrganizationID
 * @property int $UploadedByID
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\Security\Member UploadedBy()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\OrgEvent[] Events()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] People()
 * @method \SilverStripe\ORM\ManyManyList|\App\Skript\ScriptRole[] Characters()
 * @method \SilverStripe\ORM\ManyManyList|\App\Photos\PhotoTag[] Tags()
 */
class Photo extends DataObject
{
    private static $db = [
        "Title"        => "Varchar(255)",
        "Description"  => "Text",
        // Freitext, z.B. "Steffen Kahl" oder "CC BY 4.0 Max Muster" — angezeigt mit ©
        "Copyright"    => "Varchar(255)",
        "StoragePath"  => "Varchar(255)",
        "ThumbPath"    => "Varchar(255)",
        "OriginalName" => "Varchar(255)",
        // SHA1 der Ansichts-Datei — gleiche Datei in derselben Organisation nicht doppelt
        "Hash"         => "Varchar(40)",
        "Width"        => "Int",
        "Height"       => "Int",
        "FileSize"     => "Int",
        "ThumbSize"    => "Int",
        // Aus den EXIF-Daten (Aufnahmezeit bzw. GPS), sonst leer. Double kann nicht leer
        // sein — ohne GPS steht dort 0/0, siehe hasCoordinates()
        "TakenAt"      => "Datetime",
        "Latitude"     => "Double",
        "Longitude"    => "Double",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
        "UploadedBy"   => Member::class,
    ];

    private static $many_many = [
        "Events"     => OrgEvent::class,
        "People"     => Member::class,
        "Characters" => ScriptRole::class,
        "Tags"       => PhotoTag::class,
    ];

    private static $indexes = [
        "OrgHash"     => ["type" => "index", "columns" => ["OrganizationID", "Hash"]],
        "OrgTakenAt"  => ["type" => "index", "columns" => ["OrganizationID", "TakenAt"]],
    ];

    private static $default_sort = '"TakenAt" DESC, "Created" DESC';

    private static $field_labels = [
        "Title"        => "Titel",
        "Description"  => "Beschreibung",
        "Copyright"    => "Copyright",
        "OriginalName" => "Dateiname",
        "TakenAt"      => "Aufgenommen",
        "Organization" => "Organisation",
        "UploadedBy"   => "Hochgeladen von",
        "People"       => "Personen",
        "Characters"   => "Charaktere",
        "Tags"         => "Schlagworte",
    ];

    private static $summary_fields = [
        "OriginalName"       => "Dateiname",
        "Organization.Title" => "Organisation",
        "TakenAt"            => "Aufgenommen",
        "Created"            => "Hochgeladen",
    ];

    private static $table_name = 'Photo';
    private static $singular_name = "Foto";
    private static $plural_name = "Fotos";

    /** Ob es einen Aufnahmeort gibt — 0/0 heißt "keiner" (liegt im Atlantik, kommt bei Fotos nicht vor) */
    public function hasCoordinates(): bool
    {
        return (float) $this->Latitude !== 0.0 || (float) $this->Longitude !== 0.0;
    }

    /** Alle Mitglieder der Organisation sehen deren Fotos */
    public function isViewableBy(?Member $member): bool
    {
        $org = $this->Organization();
        return $member && $org && $org->exists() && $org->EnablePhotos && $member->isActiveMemberOfOrg($org);
    }

    /** Titel, Beschreibung, Events und Tags ändern: wer es hochgeladen hat oder PHOTOS_MANAGE */
    public function isEditableBy(?Member $member): bool
    {
        if (!$this->isViewableBy($member)) {
            return false;
        }
        return (int) $this->UploadedByID === (int) $member->ID
            || $member->hasOrgPermission($this->Organization(), OrgPermissions::PHOTOS_MANAGE);
    }

    public function isDeletableBy(?Member $member): bool
    {
        return $this->isEditableBy($member);
    }

    public function storage(): ?BunnyStorage
    {
        $config = PhotoStorageConfig::forOrganization((int) $this->OrganizationID);
        return $config && $config->isComplete() ? $config->client() : null;
    }

    /** Die Dateien bei Bunny mitlöschen (best effort — ein Fehler hält das Löschen nicht auf) */
    protected function onAfterDelete()
    {
        parent::onAfterDelete();

        $this->Events()->removeAll();
        $this->People()->removeAll();
        $this->Characters()->removeAll();
        $this->Tags()->removeAll();

        $storage = $this->storage();
        if (!$storage) {
            return;
        }
        foreach (array_filter([$this->StoragePath, $this->ThumbPath]) as $path) {
            try {
                $storage->delete($path);
            } catch (\RuntimeException $e) {
                Injector::inst()->get(LoggerInterface::class)->warning(
                    sprintf('Foto %d: %s konnte bei Bunny nicht gelöscht werden: %s', $this->ID, $path, $e->getMessage())
                );
            }
        }
    }
}
