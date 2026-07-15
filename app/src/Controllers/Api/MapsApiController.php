<?php

namespace App\Controllers\Api;

use App\Maps\Map;
use App\Maps\MapLayer;
use App\Maps\MapPOI;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use App\Controllers\ApiController;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Class \App\Controllers\Api\MapsApiController
 *
 */
class MapsApiController extends ApiController
{
    private static $url_segment = 'api/v1/maps';

    private static $allowed_actions = [
        'index',
        'view',
        'managedorgs',
        'createmap',
        'deletemap',
        'uploadbackgroundimage',
        'savelayer',
        'deletelayer',
        'uploadlayerimage',
        'createlayer',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $organizationIDs = $member->getOrganizationIDs();

            $canManageAny = false;
            foreach ($organizationIDs as $orgID) {
                $orgCheck = Organization::get()->byID($orgID);
                if ($orgCheck && $orgCheck->exists() && (
                    $member->hasOrgPermission($orgCheck, OrgPermissions::MAPS_MANAGE_MAPS)
                    || $member->hasOrgPermission($orgCheck, OrgPermissions::MAPS_MANAGE_LAYERS)
                )) {
                    $canManageAny = true;
                    break;
                }
            }

            if (empty($organizationIDs)) {
                return $this->jsonResponse(['maps' => [], 'canManageAny' => $canManageAny]);
            }

            $maps = Map::get()
                ->filter(['Active' => true, 'ParentID' => $organizationIDs])
                ->sort('Created', 'DESC');

            $mapsData = [];
            foreach ($maps as $map) {
                $org = $map->Parent();
                $mapsData[] = [
                    'id'                  => $map->ID,
                    'title'               => $map->Title,
                    'shortText'           => $map->ShortText,
                    'thumbnailUrl'        => $map->BackgroundImage()->exists()
                        ? $map->BackgroundImage()->FillMax(400, 300)->getURL()
                        : null,
                    'organizationTitle'   => $org->exists() ? $org->Title : null,
                    'organizationLogoUrl' => $org->exists() && $org->Logo()->exists()
                        ? $org->Logo()->ScaleWidth(80)->getURL()
                        : null,
                ];
            }

            return $this->jsonResponse(['maps' => $mapsData, 'canManageAny' => $canManageAny]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler beim Laden der Lagepläne: ' . $e->getMessage(), 500);
        }
    }

    public function view(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $mapID = $request->param('ID');
            $map   = Map::get()->byID($mapID);

            if (!$map || !$map->exists()) {
                return $this->errorResponse('Lageplan nicht gefunden', 404);
            }

            $organizationIDs = $member->getOrganizationIDs();
            if (!in_array($map->ParentID, $organizationIDs)) {
                return $this->errorResponse('Zugriff verweigert', 403);
            }

            $org = $map->Parent();
            $canEdit = $member->hasOrgPermission($org, OrgPermissions::MAPS_MANAGE_MAPS);

            $layersData = [];
            foreach ($map->MapLayers() as $layer) {
                $poisData = [];
                foreach ($layer->POIs() as $poi) {
                    $poisData[] = [
                        'id'          => $poi->ID,
                        'title'       => $poi->Title,
                        'description' => $poi->Description,
                        'active'      => (bool) $poi->Active,
                        'position'    => $poi->Coordinates,
                        'markerColor' => $poi->getMarkerColor(),
                        'markerText'  => $poi->getMarkerText(),
                    ];
                }
                $layersData[] = [
                    'id'         => $layer->ID,
                    'title'      => $layer->Title,
                    'active'     => (bool) $layer->Active,
                    'imageUrl'   => $layer->Image()->exists() ? $layer->Image()->getAbsoluteURL() : '',
                    'layerColor' => $layer->LayerColor ?: '#999999',
                    'pois'       => $poisData,
                ];
            }

            return $this->jsonResponse([
                'map' => [
                    'id'                    => $map->ID,
                    'title'                 => $map->Title,
                    'shortText'             => $map->ShortText,
                    'backgroundImage'       => $map->BackgroundImage()->exists()
                        ? $map->BackgroundImage()->getAbsoluteURL()
                        : null,
                    'coordinatesUpperLeft'  => $map->CoordinatesUpperLeft,
                    'coordinatesUpperRight' => $map->CoordinatesUpperRight,
                    'coordinatesLowerLeft'  => $map->CoordinatesLowerLeft,
                    'coordinatesLowerRight' => $map->CoordinatesLowerRight,
                    'layers'                => $layersData,
                    'canEdit'               => $canEdit,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler beim Laden des Lageplans: ' . $e->getMessage(), 500);
        }
    }

    public function managedorgs(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $managedOrgIDs = [];
            foreach ($member->getOrganizationIDs() as $orgID) {
                $orgCheck = Organization::get()->byID($orgID);
                if ($orgCheck && $orgCheck->exists() && $member->hasOrgPermission($orgCheck, OrgPermissions::MAPS_MANAGE_MAPS)) {
                    $managedOrgIDs[] = $orgID;
                }
            }

            $orgs = Organization::get()->filter('ID', $managedOrgIDs ?: [0])->sort('Title ASC');
            $data = [];
            foreach ($orgs as $org) {
                $data[] = ['id' => $org->ID, 'title' => $org->Title];
            }

            return $this->jsonResponse(['organizations' => $data]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function deletemap(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        try {
            $mapID = $request->param('ID');
            $map   = Map::get()->byID($mapID);

            if (!$map || !$map->exists()) {
                return $this->errorResponse('Lageplan nicht gefunden', 404);
            }

            if (!$member->hasOrgPermission($map->Parent(), OrgPermissions::MAPS_MANAGE_MAPS)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $map->delete();

            return $this->successResponse([], 'Lageplan gelöscht');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler beim Löschen: ' . $e->getMessage(), 500);
        }
    }

    public function createmap(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        try {
            $data = $this->getJsonBody();

            if (empty($data['title'])) {
                return $this->errorResponse('Titel ist erforderlich', 400);
            }

            $orgID = (int) ($data['organizationId'] ?? 0);
            $org   = Organization::get()->byID($orgID);
            if (!$org || !$org->exists()) {
                return $this->errorResponse('Organisation nicht gefunden', 404);
            }

            if (!$member->hasOrgPermission($org, OrgPermissions::MAPS_MANAGE_MAPS)) {
                return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
            }

            $map                       = Map::create();
            $map->Title                = $data['title'];
            $map->ShortText            = $data['shortText'] ?? '';
            $map->ParentID             = $orgID;
            $map->AuthorID             = $member->ID;
            $map->CoordinatesUpperLeft  = $data['coordinatesUpperLeft'] ?? '';
            $map->CoordinatesUpperRight = $data['coordinatesUpperRight'] ?? '';
            $map->CoordinatesLowerLeft  = $data['coordinatesLowerLeft'] ?? '';
            $map->CoordinatesLowerRight = $data['coordinatesLowerRight'] ?? '';
            $map->Active               = true;
            $map->write();

            return $this->successResponse(['mapId' => $map->ID], 'Lageplan erstellt');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler beim Erstellen: ' . $e->getMessage(), 500);
        }
    }

    public function uploadbackgroundimage(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $mapID = $request->param('ID');
            $map   = Map::get()->byID($mapID);

            if (!$map || !$map->exists()) {
                return $this->errorResponse('Lageplan nicht gefunden', 404);
            }

            if (!$member->hasOrgPermission($map->Parent(), OrgPermissions::MAPS_MANAGE_MAPS)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                return $this->errorResponse('Keine Datei hochgeladen', 400);
            }

            $validator = new Upload_Validator();
            $validator->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $validator->setAllowedMaxFileSize(10 * 1024 * 1024);

            $upload = new Upload();
            $upload->setValidator($validator);

            $file   = new Image();
            $result = $upload->loadIntoFile($_FILES['image'], $file, 'Maps');

            if (!$result) {
                return $this->errorResponse('Upload-Fehler: ' . implode(', ', $upload->getErrors()), 400);
            }

            $file->write();
            $file->publishSingle();

            $map->BackgroundImageID = $file->ID;
            $map->write();

            return $this->successResponse(['imageUrl' => $file->getAbsoluteURL()], 'Bild hochgeladen');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function deletelayer(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        try {
            $layerID = $request->param('ID');
            $layer   = MapLayer::get()->byID($layerID);

            if (!$layer || !$layer->exists()) {
                return $this->errorResponse('Ebene nicht gefunden', 404);
            }

            $layerMap = $layer->Parent();
            $layerOrg = ($layerMap && $layerMap->exists()) ? $layerMap->Parent() : null;
            if (!$layerOrg || !$layerOrg->exists() || !$member->hasOrgPermission($layerOrg, OrgPermissions::MAPS_MANAGE_LAYERS)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $layer->delete();

            return $this->successResponse([], 'Ebene gelöscht');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler beim Löschen: ' . $e->getMessage(), 500);
        }
    }

    public function savelayer(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        try {
            $layerID = $request->param('ID');
            if (!$layerID) {
                return $this->errorResponse('Keine Layer-ID angegeben', 400);
            }

            $layer = MapLayer::get()->byID($layerID);
            if (!$layer || !$layer->exists()) {
                return $this->errorResponse('Ebene nicht gefunden', 404);
            }

            $layerMap = $layer->Parent();
            $layerOrg = ($layerMap && $layerMap->exists()) ? $layerMap->Parent() : null;
            if (!$layerOrg || !$layerOrg->exists() || !$member->hasOrgPermission($layerOrg, OrgPermissions::MAPS_MANAGE_LAYERS)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $data = $this->getJsonBody();
            if (!$data) {
                return $this->errorResponse('Ungültige Daten', 400);
            }

            if (isset($data['title'])) {
                $layer->Title = $data['title'];
            }
            if (isset($data['description'])) {
                $layer->Description = $data['description'];
            }
            if (isset($data['layerColor'])) {
                $layer->LayerColor = $data['layerColor'];
            }

            $sentPoiIds = [];

            if (isset($data['pois']) && is_array($data['pois'])) {
                foreach ($data['pois'] as $poiData) {
                    $isNew = !empty($poiData['isNew']);

                    if ($isNew) {
                        $poi           = MapPOI::create();
                        $poi->ParentID = $layer->ID;
                        $poi->Active   = true;
                    } else {
                        if (!isset($poiData['id'])) {
                            continue;
                        }
                        $poi = MapPOI::get()->byID((int) $poiData['id']);
                        if (!$poi || $poi->ParentID != $layer->ID) {
                            continue;
                        }
                    }

                    if (isset($poiData['title'])) {
                        $poi->Title = $poiData['title'];
                    }
                    if (isset($poiData['description'])) {
                        $poi->Description = $poiData['description'];
                    }
                    if (isset($poiData['position'])) {
                        $poi->Coordinates = $poiData['position'];
                    }
                    if (isset($poiData['markerColor'])) {
                        $poi->MarkerColor = $poiData['markerColor'];
                    }
                    if (isset($poiData['markerText'])) {
                        $poi->MarkerText = $poiData['markerText'];
                    }
                    if (isset($poiData['active'])) {
                        $poi->Active = (bool) $poiData['active'];
                    }

                    $poi->write();
                    $sentPoiIds[] = $poi->ID; // Track real ID (handles both new and existing)
                }

                foreach ($layer->POIs() as $existingPOI) {
                    if (!in_array($existingPOI->ID, $sentPoiIds)) {
                        $existingPOI->delete();
                    }
                }
            }

            $layer->write();

            return $this->successResponse([], 'Ebene gespeichert');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler beim Speichern: ' . $e->getMessage(), 500);
        }
    }

    public function uploadlayerimage(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $layerID = $request->param('ID');
            $layer   = MapLayer::get()->byID($layerID);

            if (!$layer || !$layer->exists()) {
                return $this->errorResponse('Ebene nicht gefunden', 404);
            }

            $layerMap = $layer->Parent();
            $layerOrg = ($layerMap && $layerMap->exists()) ? $layerMap->Parent() : null;
            if (!$layerOrg || !$layerOrg->exists() || !$member->hasOrgPermission($layerOrg, OrgPermissions::MAPS_MANAGE_LAYERS)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                return $this->errorResponse('Keine Datei hochgeladen', 400);
            }

            $validator = new Upload_Validator();
            $validator->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $validator->setAllowedMaxFileSize(10 * 1024 * 1024);

            $upload = new Upload();
            $upload->setValidator($validator);

            $file   = new Image();
            $result = $upload->loadIntoFile($_FILES['image'], $file, 'MapLayers');

            if (!$result) {
                return $this->errorResponse('Upload-Fehler: ' . implode(', ', $upload->getErrors()), 400);
            }

            $file->write();
            $file->publishSingle();

            $layer->ImageID = $file->ID;
            $layer->write();

            return $this->successResponse(['imageUrl' => $file->getAbsoluteURL()], 'Bild hochgeladen');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function createlayer(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        try {
            $mapID = $request->param('ID');
            $map   = Map::get()->byID($mapID);

            if (!$map || !$map->exists()) {
                return $this->errorResponse('Lageplan nicht gefunden', 404);
            }

            if (!$member->hasOrgPermission($map->Parent(), OrgPermissions::MAPS_MANAGE_LAYERS)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $data = $this->getJsonBody();

            $layer             = MapLayer::create();
            $layer->Title      = $data['title'] ?? 'Neue Ebene';
            $layer->Description = $data['description'] ?? '';
            $layer->LayerColor = $data['layerColor'] ?? '#999999';
            $layer->ParentID   = $map->ID;
            $layer->Active     = true;
            $layer->write();

            return $this->successResponse([
                'layerId' => $layer->ID,
                'layer'   => [
                    'id'         => $layer->ID,
                    'title'      => $layer->Title,
                    'active'     => true,
                    'imageUrl'   => '',
                    'layerColor' => $layer->LayerColor,
                    'pois'       => [],
                ],
            ], 'Ebene erstellt');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }
}
