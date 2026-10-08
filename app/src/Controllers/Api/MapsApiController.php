<?php

namespace App\Controllers\Api;

use App\Maps\Map;
use App\Maps\MapLayer;
use App\Maps\MapPOI;
use App\Inventory\InventoryItem;
use App\Inventory\InventoryRental;
use App\Rooms\Room;
use App\Teams\OrgEvent;
use App\Teams\OrgEventItemPlacement;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use App\Controllers\ApiController;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

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
        'reorderlayers',
        'eventPlans',
        'eventPlanAttach',
        'eventPlanDetach',
        'eventPlanView',
        'eventPlacementSave',
    ];

    /** Ausleihen, deren Objekte auf den Lageplänen eines Events erscheinen */
    private const EVENT_RENTAL_STATUSES = ['requested', 'approved', 'handed_over', 'returned'];

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
                    'organizationLogoUrl' => $org->exists() ? $org->RenderLogo(80) : null,
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

            return $this->jsonResponse([
                'map' => $this->formatMap($map) + ['canEdit' => $canEdit],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler beim Laden des Lageplans: ' . $e->getMessage(), 500);
        }
    }

    /** Lageplan mit Ebenen und Markern im Format des MapRenderers */
    private function formatMap(Map $map): array
    {
        $layersData = [];
        foreach ($map->MapLayers() as $layer) {
            $poisData = [];
            foreach ($layer->POIs() as $poi) {
                $poiRoom = $poi->Room();
                $poisData[] = [
                    'id'          => $poi->ID,
                    'title'       => $poi->Title,
                    'description' => $poi->Description,
                    'active'      => (bool) $poi->Active,
                    'position'    => $poi->Coordinates,
                    'markerColor' => $poi->getMarkerColor(),
                    'markerText'  => $poi->getMarkerText(),
                    'type'        => $poi->Type ?: 'marker',
                    'roomId'      => ($poiRoom && $poiRoom->exists()) ? $poiRoom->ID : null,
                    'room'        => ($poiRoom && $poiRoom->exists()) ? [
                        'id'    => $poiRoom->ID,
                        'title' => $poiRoom->Title,
                    ] : null,
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

        return [
            'id'                    => $map->ID,
            'organizationId'        => $map->ParentID,
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
        ];
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
                $data[] = ['id' => $org->ID, 'title' => $org->Title, 'logoUrl' => $org->RenderLogo(40)];
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
            $validator->setAllowedExtensions(['jpg', 'jpeg', 'png', 'webp']);
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
            if (isset($data['active'])) {
                $layer->Active = (bool) $data['active'];
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

                    $type = $poiData['type'] ?? 'marker';
                    if ($type === 'room') {
                        $roomID = (int) ($poiData['roomId'] ?? 0);
                        $room   = $roomID ? Room::get()->byID($roomID) : null;
                        if (!$room || !$room->exists() || (int) $room->OrganizationID !== (int) $layerOrg->ID) {
                            return $this->errorResponse('Ungültiger oder fremder Raum für Raummarker', 400);
                        }
                        $poi->Type   = 'room';
                        $poi->RoomID = $roomID;
                    } else {
                        $poi->Type   = 'marker';
                        $poi->RoomID = 0;
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
            $validator->setAllowedExtensions(['jpg', 'jpeg', 'png', 'webp']);
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
            $layer->SortOrder  = $map->MapLayers()->count();
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

    /** POST /api/v1/maps/reorderlayers/$ID — body: { layerIds: [id, id, ...] } in the new display order */
    public function reorderlayers(HTTPRequest $request): HTTPResponse
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
            $layerIDs = $data['layerIds'] ?? null;
            if (!is_array($layerIDs)) {
                return $this->errorResponse('Ungültige Daten', 400);
            }

            foreach ($layerIDs as $sortOrder => $layerID) {
                $layer = MapLayer::get()->byID((int) $layerID);
                if (!$layer || $layer->ParentID != $map->ID) {
                    continue; // ignore stray/foreign IDs rather than failing the whole reorder
                }
                $layer->SortOrder = $sortOrder;
                $layer->write();
            }

            return $this->successResponse([], 'Reihenfolge gespeichert');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler beim Speichern der Reihenfolge: ' . $e->getMessage(), 500);
        }
    }

    // ---------------------------------------------------------------------
    // Lagepläne eines Events (OrgEvent): ausgeliehene Objekte platzieren
    // ---------------------------------------------------------------------

    /** Event per ID oder URL-Segment — nur für Mitglieder der Organisation */
    private function findEventFor(string $key, Member $member): ?OrgEvent
    {
        $event = ctype_digit($key)
            ? OrgEvent::get()->byID((int) $key)
            : ($key !== '' ? OrgEvent::get()->filter('URLSegment', $key)->first() : null);
        return $event && $event->isInternalFor($member) ? $event : null;
    }

    /** Lagepläne zuordnen und Objekte platzieren darf, wer das Event verwalten oder Lagepläne bearbeiten darf */
    private function canManageEventPlans(OrgEvent $event, Member $member): bool
    {
        return $event->canBeManagedBy($member)
            || $member->hasOrgPermission($event->Organization(), OrgPermissions::MAPS_MANAGE_LAYERS);
    }

    /**
     * Alle Objekte aus den Ausleihen des Events (ohne abgelehnte/stornierte), mit
     * Platz und Notiz. Objekte, die schon einen Eintrag haben, aber in keiner
     * Ausleihe mehr stehen, bleiben sichtbar — sonst ginge z.B. die DMX-Adresse
     * stillschweigend verloren. `Number` ist die laufende Nummer auf den Markern.
     */
    private function eventItems(OrgEvent $event): array
    {
        $placements = [];
        foreach ($event->ItemPlacements() as $placement) {
            $placements[(int) $placement->ItemID] = $placement;
        }

        $entries = [];
        foreach ($event->Rentals()->filter('Status', self::EVENT_RENTAL_STATUSES)->sort('Created', 'ASC') as $rental) {
            foreach ($rental->Items() as $item) {
                $entries[$item->ID] ??= $this->formatEventItem($item, $rental, $placements[$item->ID] ?? null);
            }
        }
        foreach ($placements as $itemID => $placement) {
            if (!isset($entries[$itemID]) && $placement->Item()->exists()) {
                $entries[$itemID] = $this->formatEventItem($placement->Item(), null, $placement);
            }
        }

        $entries = array_values($entries);
        usort($entries, fn ($a, $b) => strnatcasecmp((string) $a['Title'], (string) $b['Title'])
            ?: strnatcasecmp((string) $a['InventoryNumber'], (string) $b['InventoryNumber']));
        foreach ($entries as $i => &$entry) {
            $entry['Number'] = $i + 1;
        }
        return $entries;
    }

    private function formatEventItem(InventoryItem $item, ?InventoryRental $rental, ?OrgEventItemPlacement $placement): array
    {
        $type = $item->Type();
        $placed = $placement && $placement->isPlaced();
        return [
            'ItemID'            => $item->ID,
            'Title'             => $item->Title,
            'InventoryNumber'   => $item->InventoryNumber,
            'Type'              => $type && $type->exists() ? $type->Title : null,
            'RentalID'          => $rental?->ID,
            'RentalStatus'      => $rental?->Status,
            'RentalStatusLabel' => $rental ? (InventoryRental::STATUS_LABELS[$rental->Status] ?? $rental->Status) : null,
            'Note'              => $placement ? (string) $placement->Note : '',
            'MapID'             => $placed ? (int) $placement->MapID : null,
            'Position'          => $placed ? $placement->Coordinates : null,
        ];
    }

    /** Gesamter Stand für die Karten "Lagepläne" und "Inventar" der Event-Seite */
    private function formatEventPlans(OrgEvent $event, Member $member): array
    {
        $items = $this->eventItems($event);
        $placedCounts = array_count_values(array_filter(array_column($items, 'MapID')));

        $plans = [];
        $linkedIDs = [];
        foreach ($event->SitePlans()->sort('Title', 'ASC') as $map) {
            $linkedIDs[] = $map->ID;
            $plans[] = [
                'ID'           => $map->ID,
                'Title'        => $map->Title,
                'ThumbnailURL' => $map->BackgroundImage()->exists() ? $map->BackgroundImage()->FillMax(400, 300)->getURL() : null,
                'PlacedCount'  => $placedCounts[$map->ID] ?? 0,
            ];
        }

        $canManage = $this->canManageEventPlans($event, $member);
        // Auch Lagepläne anderer eigener Organisationen (z.B. des Veranstaltungsorts) —
        // über das Event sehen ihn dann alle Mitglieder der Event-Organisation
        $available = [];
        if ($canManage) {
            $maps = Map::get()->filter(['ParentID' => $member->getOrganizationIDs() ?: [0], 'Active' => true])
                ->exclude('ID', $linkedIDs ?: [0])
                ->sort('Title', 'ASC');
            foreach ($maps as $map) {
                $available[] = [
                    'ID'                => $map->ID,
                    'Title'             => $map->Title,
                    'OrganizationTitle' => $map->Parent()->exists() ? $map->Parent()->Title : null,
                    'IsEventOrg'        => (int) $map->ParentID === (int) $event->OrganizationID,
                ];
            }
        }

        return [
            'plans'          => $plans,
            'availablePlans' => $available,
            'items'          => $items,
            'CanManage'      => $canManage,
            'CanRequestRental' => $member->hasOrgPermission($event->Organization(), OrgPermissions::INVENTORY_REQUEST_RENTAL),
        ];
    }

    /** GET /api/v1/maps/eventPlans/$ID — Lagepläne und ausgeliehene Objekte eines Events */
    public function eventPlans(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $event = $this->findEventFor((string) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        return $this->jsonResponse($this->formatEventPlans($event, $member));
    }

    /** POST /api/v1/maps/eventPlanAttach/$ID {MapID} — ordnet einen Lageplan der Organisation zu */
    public function eventPlanAttach(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $event = $this->findEventFor((string) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        if (!$this->canManageEventPlans($event, $member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }
        $body = $this->getJsonBody();
        $map = Map::get()->filter(['ID' => (int) ($body['MapID'] ?? 0), 'ParentID' => $member->getOrganizationIDs() ?: [0]])->first();
        if (!$map) {
            return $this->errorResponse('Lageplan nicht gefunden', 404);
        }
        $event->SitePlans()->add($map);

        return $this->successResponse($this->formatEventPlans($event, $member), 'Lageplan hinzugefügt');
    }

    /**
     * DELETE /api/v1/maps/eventPlanDetach/$ID?map={MapID}
     * Löst den Lageplan vom Event. Die Objekte darauf gelten wieder als nicht
     * platziert, ihre Notizen bleiben erhalten.
     */
    public function eventPlanDetach(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $event = $this->findEventFor((string) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        if (!$this->canManageEventPlans($event, $member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }
        $map = $event->SitePlans()->byID((int) $request->getVar('map'));
        if (!$map) {
            return $this->errorResponse('Lageplan gehört nicht zu diesem Event', 404);
        }
        $event->SitePlans()->remove($map);
        foreach ($event->ItemPlacements()->filter('MapID', $map->ID) as $placement) {
            $placement->MapID = 0;
            $placement->Coordinates = null;
            $this->writeOrDropPlacement($placement);
        }

        return $this->successResponse($this->formatEventPlans($event, $member), 'Lageplan vom Event gelöst');
    }

    /**
     * GET /api/v1/maps/eventPlanView/$ID?map={MapID}
     * Ein Lageplan des Events mit seinen Ebenen (nur lesend) und den Objekten des Events.
     */
    public function eventPlanView(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $event = $this->findEventFor((string) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        $map = $event->SitePlans()->byID((int) $request->getVar('map'));
        if (!$map) {
            return $this->errorResponse('Lageplan gehört nicht zu diesem Event', 404);
        }

        $state = $this->formatEventPlans($event, $member);
        return $this->jsonResponse([
            'map'   => $this->formatMap($map),
            'event' => [
                'ID'         => $event->ID,
                'Title'      => $event->Title,
                'URLSegment' => $event->URLSegment,
            ],
            'plans'     => $state['plans'],
            'items'     => $state['items'],
            'CanManage' => $state['CanManage'],
        ]);
    }

    /**
     * POST /api/v1/maps/eventPlacementSave/$ID {ItemID, MapID?, Position?, Note?}
     * Platziert ein Objekt des Events (MapID + Position "lat,lng"), nimmt es vom
     * Lageplan (MapID null/0) und/oder ändert die Notiz. Nur übergebene Schlüssel
     * werden geändert. Antwort: die aktualisierte Objektliste.
     */
    public function eventPlacementSave(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $event = $this->findEventFor((string) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        if (!$this->canManageEventPlans($event, $member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $itemID = (int) ($body['ItemID'] ?? 0);
        if (!in_array($itemID, array_column($this->eventItems($event), 'ItemID'), true)) {
            return $this->errorResponse('Das Objekt ist für dieses Event nicht ausgeliehen', 400);
        }

        $placement = $event->ItemPlacements()->filter('ItemID', $itemID)->first()
            ?? OrgEventItemPlacement::create(['EventID' => $event->ID, 'ItemID' => $itemID]);

        if (array_key_exists('MapID', $body)) {
            $mapID = (int) $body['MapID'];
            if ($mapID) {
                if (!$event->SitePlans()->byID($mapID)) {
                    return $this->errorResponse('Lageplan gehört nicht zu diesem Event', 400);
                }
                $position = $this->normalizePosition((string) ($body['Position'] ?? ''));
                if (!$position) {
                    return $this->errorResponse('Ungültige Position', 400);
                }
                $placement->MapID = $mapID;
                $placement->Coordinates = $position;
            } else {
                $placement->MapID = 0;
                $placement->Coordinates = null;
            }
        }
        if (array_key_exists('Note', $body)) {
            $note = trim((string) $body['Note']);
            if (mb_strlen($note) > 2000) {
                return $this->errorResponse('Die Notiz ist zu lang', 400);
            }
            $placement->Note = $note;
        }
        $this->writeOrDropPlacement($placement);

        return $this->successResponse(['items' => $this->eventItems($event)], 'Gespeichert');
    }

    /**
     * "lat,lng" wie bei MapPOI — auf 8 Nachkommastellen, da JavaScript kleine Werte
     * (Lagepläne ohne echte Koordinaten liegen um 0) auch als "1e-7" schreibt
     */
    private function normalizePosition(string $value): ?string
    {
        $parts = array_map('trim', explode(',', $value));
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }
        [$lat, $lng] = array_map('floatval', $parts);
        if (abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }
        $format = fn (float $n) => rtrim(rtrim(sprintf('%.8F', $n), '0'), '.') ?: '0';
        return $format($lat) . ',' . $format($lng);
    }

    /** Ein Eintrag ohne Platz und ohne Notiz hat keinen Inhalt mehr */
    private function writeOrDropPlacement(OrgEventItemPlacement $placement): void
    {
        if (!$placement->MapID && trim((string) $placement->Note) === '') {
            if ($placement->isInDB()) {
                $placement->delete();
            }
            return;
        }
        $placement->write();
    }
}
