<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Links\TeamLink;
use App\Links\TeamLinkType;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\LinkField\Models\ExternalLink;
use SilverStripe\LinkField\Models\FileLink;

/**
 * Class \App\Controllers\Api\LinksApiController
 *
 */
class LinksApiController extends ApiController
{
    private static $url_segment = 'api/v1/links';

    private static $allowed_actions = [
        'index',
        'update',
        'remove',
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $method = $request->httpMethod();

        if ($method === 'GET') {
            return $this->listLinks($request, $member);
        }

        if ($method === 'POST') {
            return $this->createLink($request, $member);
        }

        return $this->errorResponse('Method not allowed', 405);
    }

    private function listLinks(HTTPRequest $request, $member): HTTPResponse
    {
        try {
            $organizationIDs = $member->getOrganizationIDs();

            // Find orgs where der Nutzer Links verwalten darf
            $adminOrgIDs = [];
            $adminOrgs = [];
            foreach ($organizationIDs as $orgID) {
                $org = Organization::get()->byID($orgID);
                if ($org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::LINKS_MANAGE)) {
                    $adminOrgIDs[] = $orgID;
                    $adminOrgs[] = [
                        'ID'    => $org->ID,
                        'Title' => $org->Title,
                    ];
                }
            }

            if (empty($organizationIDs)) {
                $types = TeamLinkType::get();
                $typesData = [];
                foreach ($types as $type) {
                    $typesData[] = ['ID' => $type->ID, 'Title' => $type->Title];
                }
                return $this->jsonResponse([
                    'links'       => [],
                    'types'       => $typesData,
                    'adminOrgIDs' => $adminOrgIDs,
                    'adminOrgs'   => $adminOrgs,
                ]);
            }

            $teamLinks = TeamLink::get()
                ->filter(['ParentID' => $organizationIDs])
                ->sort('SortOrder ASC, Title ASC');

            $linksData = [];
            foreach ($teamLinks as $teamLink) {
                try {
                    $btn = $teamLink->Button();
                    $linkKind = 'external';
                    $url = null;
                    $fileName = null;
                    $openInNew = false;

                    if ($btn && $btn->exists()) {
                        if ($btn instanceof FileLink) {
                            $linkKind = 'file';
                            $file = $btn->File();
                            $url = $file && $file->exists() ? $file->getURL(true) : null;
                            $fileName = $file && $file->exists() ? basename($file->getFilename() ?? '') : null;
                            $openInNew = (bool) $btn->OpenInNew;
                        } else {
                            $linkKind = 'external';
                            $url = $btn->ExternalUrl ?? null;
                            $openInNew = (bool) $btn->OpenInNew;
                        }
                    }

                    $org = $teamLink->Parent();
                    $type = $teamLink->Type();

                    $linksData[] = [
                        'ID'          => $teamLink->ID,
                        'Title'       => $teamLink->Title,
                        'OrgID'       => $org ? $org->ID : null,
                        'OrgTitle'    => $org ? $org->Title : null,
                        'OrgUsername' => $org ? ($org->Username ?: null) : null,
                        'TypeID'      => ($type && $type->exists()) ? $type->ID : null,
                        'TypeTitle'   => ($type && $type->exists()) ? $type->Title : null,
                        'LinkKind'    => $linkKind,
                        'URL'         => $url,
                        'FileName'    => $fileName,
                        'OpenInNew'   => $openInNew,
                    ];
                } catch (\Exception $e) {
                    error_log('Error processing TeamLink ' . $teamLink->ID . ': ' . $e->getMessage());
                }
            }

            $types = TeamLinkType::get()->sort('Title ASC');
            $typesData = [];
            foreach ($types as $type) {
                $typesData[] = ['ID' => $type->ID, 'Title' => $type->Title];
            }

            return $this->jsonResponse([
                'links'       => $linksData,
                'types'       => $typesData,
                'adminOrgIDs' => $adminOrgIDs,
                'adminOrgs'   => $adminOrgs,
            ]);
        } catch (\Exception $e) {
            error_log('LinksApiController::listLinks error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Fehler beim Laden der Links: ' . $e->getMessage(), 500);
        }
    }

    private function createLink(HTTPRequest $request, $member): HTTPResponse
    {
        try {
            $contentType = $request->getHeader('Content-Type') ?? '';
            $isMultipart = strpos($contentType, 'multipart/form-data') !== false;

            if ($isMultipart) {
                $title     = $request->postVar('title') ?? '';
                $orgId     = (int) ($request->postVar('orgId') ?? 0);
                $typeId    = (int) ($request->postVar('typeId') ?? 0);
                $url       = $request->postVar('url') ?? '';
                $openInNew = (bool) ($request->postVar('openInNew') ?? false);
                $hasFile   = !empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
            } else {
                $data      = $this->getJsonBody();
                $title     = $data['title'] ?? '';
                $orgId     = (int) ($data['orgId'] ?? 0);
                $typeId    = (int) ($data['typeId'] ?? 0);
                $url       = $data['url'] ?? '';
                $openInNew = (bool) ($data['openInNew'] ?? false);
                $hasFile   = false;
            }

            if (empty($title)) {
                return $this->errorResponse('Titel ist erforderlich', 400);
            }

            if (!$orgId) {
                return $this->errorResponse('Organisation ist erforderlich', 400);
            }

            $org = Organization::get()->byID($orgId);
            if (!$org) {
                return $this->errorResponse('Organisation nicht gefunden', 404);
            }

            if (!$member->hasOrgPermission($org, OrgPermissions::LINKS_MANAGE)) {
                return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
            }

            // Create the Link (Button)
            if ($hasFile) {
                // File upload
                $file   = File::create();
                $upload = Upload::create();
                $upload->getValidator()->setAllowedMaxFileSize(20 * 1024 * 1024); // 20 MB
                $result = $upload->loadIntoFile($_FILES['file'], $file, '/TeamLinks/');

                if (!$result) {
                    $errors = $upload->getErrors();
                    return $this->errorResponse('Datei-Upload fehlgeschlagen: ' . implode(', ', $errors), 400);
                }

                $file->write();

                $btn            = FileLink::create();
                $btn->FileID    = $file->ID;
                $btn->OpenInNew = $openInNew;
                $btn->write();
            } else {
                if (empty($url)) {
                    return $this->errorResponse('URL oder Datei ist erforderlich', 400);
                }

                $btn              = ExternalLink::create();
                $btn->ExternalUrl = $url;
                $btn->OpenInNew   = $openInNew;
                $btn->write();
            }

            // Create TeamLink
            $teamLink           = TeamLink::create();
            $teamLink->Title    = $title;
            $teamLink->ParentID = $orgId;
            $teamLink->ButtonID = $btn->ID;

            if ($typeId) {
                $type = TeamLinkType::get()->byID($typeId);
                if ($type) {
                    $teamLink->TypeID = $typeId;
                }
            }

            $teamLink->write();
            // Publish the ownership chain: TeamLink → Button (FileLink) → File
            // FileLinkExtension adds $owns = ['File'], so publishRecursive() cascades
            // all the way to the File and moves it to the public asset store.
            $teamLink->publishRecursive();

            return $this->successResponse(['ID' => $teamLink->ID], 'Link erfolgreich erstellt');
        } catch (\Exception $e) {
            error_log('LinksApiController::createLink error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Fehler beim Erstellen des Links: ' . $e->getMessage(), 500);
        }
    }

    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        try {
            $id = (int) $request->param('ID');
            $teamLink = TeamLink::get()->byID($id);

            if (!$teamLink) {
                return $this->errorResponse('Link nicht gefunden', 404);
            }

            $org = Organization::get()->byID((int) $teamLink->ParentID);
            if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::LINKS_MANAGE)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $data      = $this->getJsonBody();
            $title     = $data['title'] ?? null;
            $typeId    = isset($data['typeId']) ? (int) $data['typeId'] : null;
            $url       = $data['url'] ?? null;
            $openInNew = isset($data['openInNew']) ? (bool) $data['openInNew'] : null;

            if ($title !== null) {
                $teamLink->Title = $title;
            }

            if ($typeId !== null) {
                $teamLink->TypeID = $typeId ?: 0;
            }

            $teamLink->write();

            // Update button if external link and url provided
            $btn = $teamLink->Button();
            if ($btn && $btn->exists() && !($btn instanceof FileLink)) {
                if ($url !== null) {
                    $btn->ExternalUrl = $url;
                }
                if ($openInNew !== null) {
                    $btn->OpenInNew = $openInNew;
                }
                $btn->write();
            }

            return $this->successResponse(['ID' => $teamLink->ID], 'Link erfolgreich aktualisiert');
        } catch (\Exception $e) {
            error_log('LinksApiController::update error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Fehler beim Aktualisieren des Links: ' . $e->getMessage(), 500);
        }
    }

    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        try {
            $id = (int) $request->param('ID');
            $teamLink = TeamLink::get()->byID($id);

            if (!$teamLink) {
                return $this->errorResponse('Link nicht gefunden', 404);
            }

            $org = Organization::get()->byID((int) $teamLink->ParentID);
            if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::LINKS_MANAGE)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $btn = $teamLink->Button();

            // Delete the TeamLink first
            $teamLink->delete();

            // Delete the associated Button and optionally its file
            if ($btn && $btn->exists()) {
                if ($btn instanceof FileLink) {
                    $file = $btn->File();
                    $btn->delete();
                    if ($file && $file->exists()) {
                        $file->delete();
                    }
                } else {
                    $btn->delete();
                }
            }

            return $this->successResponse([], 'Link erfolgreich gelöscht');
        } catch (\Exception $e) {
            error_log('LinksApiController::remove error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Fehler beim Löschen des Links: ' . $e->getMessage(), 500);
        }
    }
}
