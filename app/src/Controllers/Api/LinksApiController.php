<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Links\TeamLink;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

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
                return $this->jsonResponse([
                    'links'       => [],
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
                    $linkKind = $teamLink->LinkKind ?: 'external';
                    $url = null;
                    $fileName = null;
                    $openInNew = (bool) $teamLink->OpenInNew;

                    if ($linkKind === 'file') {
                        $file = $teamLink->File();
                        $url = $file && $file->exists() ? $file->getURL(true) : null;
                        $fileName = $file && $file->exists() ? basename($file->getFilename() ?? '') : null;
                    } else {
                        $url = $teamLink->ExternalUrl ?: null;
                    }

                    $org = $teamLink->Parent();

                    $linksData[] = [
                        'ID'          => $teamLink->ID,
                        'Title'       => $teamLink->Title,
                        'OrgID'       => $org ? $org->ID : null,
                        'OrgTitle'    => $org ? $org->Title : null,
                        'OrgUsername' => $org ? ($org->Username ?: null) : null,
                        'LinkKind'    => $linkKind,
                        'URL'         => $url,
                        'FileName'    => $fileName,
                        'OpenInNew'   => $openInNew,
                    ];
                } catch (\Exception $e) {
                    error_log('Error processing TeamLink ' . $teamLink->ID . ': ' . $e->getMessage());
                }
            }

            return $this->jsonResponse([
                'links'       => $linksData,
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
                $url       = $request->postVar('url') ?? '';
                $openInNew = (bool) ($request->postVar('openInNew') ?? false);
                $hasFile   = !empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
            } else {
                $data      = $this->getJsonBody();
                $title     = $data['title'] ?? '';
                $orgId     = (int) ($data['orgId'] ?? 0);
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

            // Create TeamLink
            $teamLink           = TeamLink::create();
            $teamLink->Title    = $title;
            $teamLink->ParentID = $orgId;
            $teamLink->OpenInNew = $openInNew;

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
                $file->publishSingle();

                $teamLink->LinkKind = 'file';
                $teamLink->FileID   = $file->ID;
            } else {
                if (empty($url)) {
                    return $this->errorResponse('URL oder Datei ist erforderlich', 400);
                }

                $teamLink->LinkKind    = 'external';
                $teamLink->ExternalUrl = $url;
            }

            $teamLink->write();

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
            $url       = $data['url'] ?? null;
            $openInNew = isset($data['openInNew']) ? (bool) $data['openInNew'] : null;

            if ($title !== null) {
                $teamLink->Title = $title;
            }

            // Update url/openInNew only for external links (matches previous behaviour of leaving file links untouched)
            if ($teamLink->LinkKind === 'external') {
                if ($url !== null) {
                    $teamLink->ExternalUrl = $url;
                }
                if ($openInNew !== null) {
                    $teamLink->OpenInNew = $openInNew;
                }
            }

            $teamLink->write();

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

            $file = $teamLink->LinkKind === 'file' ? $teamLink->File() : null;

            // Delete the TeamLink first
            $teamLink->delete();

            // Delete the associated file, if any
            if ($file && $file->exists()) {
                $file->delete();
            }

            return $this->successResponse([], 'Link erfolgreich gelöscht');
        } catch (\Exception $e) {
            error_log('LinksApiController::remove error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Fehler beim Löschen des Links: ' . $e->getMessage(), 500);
        }
    }
}
