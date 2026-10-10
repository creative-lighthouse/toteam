<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Inventory\InventoryItem;
use App\Inventory\InventoryItemType;
use App\Inventory\StorageLocation;
use App\Rooms\Room;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\StorageApiController
 *
 * Inventar-Totem, Tab "Lager": verschachtelte Lagerpunkte ({@see StorageLocation})
 * und was darin lagert (Objekte, Fahrzeuge, Räume).
 */
class StorageApiController extends ApiController
{
    private static $url_segment = 'api/v1/storage';

    private static $allowed_actions = [
        'index',
        'store',
        'update',
        'remove',
        'move',
        'place',
        'share',
        'public',
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

    /** IDs der Organisationen des Nutzers, in denen das Inventar-Totem aktiv ist */
    private function inventoryOrgIDs(Member $member): array
    {
        $orgIDs = $member->getOrganizationIDs();
        if (!$orgIDs) {
            return [];
        }
        return Organization::get()->filter(['ID' => $orgIDs, 'EnableInventory' => true])->column('ID');
    }

    /**
     * Alle Lagerpunkte, die der Nutzer sehen darf: die seiner Organisationen,
     * seine privaten und für seine Organisationen freigegebene.
     *
     * @return StorageLocation[]
     */
    private function visibleLocations(Member $member, array $orgIDs): array
    {
        $candidates = StorageLocation::get()->filterAny([
            'OrganizationID' => $orgIDs ?: [0],
            'OwnerMemberID'  => $member->ID,
            'SharedWith.ID'  => $orgIDs ?: [0],
        ]);
        $locations = [];
        foreach ($candidates as $location) {
            if (!isset($locations[$location->ID]) && $location->isViewableBy($member)) {
                $locations[$location->ID] = $location;
            }
        }
        return array_values($locations);
    }

    private function formatLocation(StorageLocation $location, Member $member, array $items, array $rooms): array
    {
        $type = $location->Type();
        $org = $location->Organization();
        $owner = $location->OwnerMember();

        return [
            'ID'             => $location->ID,
            'Title'          => $location->Title,
            'Description'    => $location->Description,
            'ParentID'       => (int) $location->ParentID ?: null,
            'SortOrder'      => (int) $location->SortOrder,
            'OwnerType'      => $location->OwnerType,
            'IsPrivate'      => $location->isPrivate(),
            'IsMine'         => $location->isOwnedBy($member),
            'OwnerName'      => $location->isPrivate()
                ? ($owner && $owner->exists() ? $owner->getDisplayName() : 'Unbekannt')
                : ($org && $org->exists() ? $org->Title : ''),
            'OrganizationID' => (int) $location->OrganizationID ?: null,
            'Organization'   => !$location->isPrivate() && $org && $org->exists()
                ? ['ID' => $org->ID, 'Title' => $org->Title, 'LogoURL' => $org->RenderLogo(40)]
                : null,
            'SharedWith'     => array_map(fn (Organization $o) => ['ID' => $o->ID, 'Title' => $o->Title], $location->getActiveSharedOrgs()),
            'TypeID'         => (int) $location->TypeID ?: null,
            'Type'           => $type && $type->exists() ? ['ID' => $type->ID, 'Title' => $type->Title] : null,
            'Fields'         => $type && $type->exists() ? $type->getFieldsForApi() : [],
            // Werte der Zusatzfelder: { "<Feld-ID>": "<Wert>" }
            'Values'         => (object) $location->getMetaValueMap(),
            // Was direkt hier lagert (nur, was der Nutzer sehen darf)
            'Items'          => $items,
            'Rooms'          => $rooms,
            // Öffentlicher Teilen-Link (auch fürs NFC-Tag) — nur für Bearbeitende
            'ShareURL'       => $location->ShareToken && $location->isEditableBy($member) ? $this->shareURL($location) : null,
            'CanEdit'        => $location->isEditableBy($member),
            'CanDelete'      => $location->isDeletableBy($member),
        ];
    }

    private function shareURL(StorageLocation $location): string
    {
        return Director::absoluteURL('/app/storage/share/' . $location->ShareToken);
    }

    private function formatLocationResponse(StorageLocation $location, Member $member): array
    {
        [$items, $rooms] = $this->contentsByLocation($member, [$location->ID]);
        return $this->formatLocation($location, $member, $items[$location->ID] ?? [], $rooms[$location->ID] ?? []);
    }

    /** Objekt/Fahrzeug als Inhalt eines Lagerpunkts bzw. von "Nicht einsortiert" */
    private function formatContentItem(InventoryItem $item, Member $member): array
    {
        $image = $item->Images()->first();
        return [
            'ID'              => $item->ID,
            'Title'           => $item->Title,
            'InventoryNumber' => $item->InventoryNumber,
            'Kind'            => $item->Kind ?: 'item',
            'Status'          => $item->Status,
            'StatusLabel'     => InventoryItem::STATUS_LABELS[$item->Status] ?? $item->Status,
            'Thumbnail'       => $image && $image->exists() ? $image->Fill(80, 80)->getURL() : null,
            // Darf per Drag & Drop einsortiert werden
            'CanEdit'         => $item->isEditableBy($member),
        ];
    }

    private function formatContentRoom(Room $room, Member $member): array
    {
        return [
            'ID'      => $room->ID,
            'Title'   => $room->Title,
            'CanEdit' => $room->isEditableBy($member),
        ];
    }

    /**
     * Objekte/Fahrzeuge und Räume in den angegebenen Lagerpunkten, gruppiert nach
     * Lagerpunkt — jeweils nur die, die der Nutzer sehen darf.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    private function contentsByLocation(Member $member, array $locationIDs): array
    {
        $items = [];
        $rooms = [];
        if (!$locationIDs) {
            return [$items, $rooms];
        }

        foreach (InventoryItem::get()->filter('StorageLocationID', $locationIDs)->exclude('Status', 'retired') as $item) {
            if ($item->isViewableBy($member)) {
                $items[(int) $item->StorageLocationID][] = $this->formatContentItem($item, $member);
            }
        }

        foreach (Room::get()->filter('StorageLocationID', $locationIDs) as $room) {
            if ($room->isViewableBy($member)) {
                $rooms[(int) $room->StorageLocationID][] = $this->formatContentRoom($room, $member);
            }
        }

        return [$items, $rooms];
    }

    /**
     * Sichtbare Objekte, Fahrzeuge und Räume ohne Lagerort ("Nicht einsortiert"),
     * gruppiert nach Besitzer: je Organisation bzw. je Person (privates Equipment).
     * Nur Gruppen mit Inhalt; eigene private Objekte zuerst, dann nach Name.
     */
    private function unassignedGroups(Member $member, array $orgIDs): array
    {
        $groups = [];
        $group = function (string $key, string $title, ?string $image, bool $isMine) use (&$groups) {
            $groups[$key] ??= ['Key' => $key, 'Title' => $title, 'Image' => $image, 'IsMine' => $isMine, 'Items' => [], 'Rooms' => []];
            return $key;
        };

        $items = InventoryItem::get()
            ->filter('StorageLocationID', 0)
            ->exclude('Status', 'retired')
            ->filterAny([
                'OrganizationID' => $orgIDs ?: [0],
                'SharedWith.ID'  => $orgIDs ?: [0],
                'OwnerMemberID'  => $member->ID,
            ]);
        $seen = [];
        foreach ($items as $item) {
            if (isset($seen[$item->ID]) || !$item->isViewableBy($member)) {
                continue;
            }
            $seen[$item->ID] = true;
            if ($item->isPrivate()) {
                $owner = $item->OwnerMember();
                $isMine = $item->isOwnedBy($member);
                $key = $group(
                    'member:' . (int) $item->OwnerMemberID,
                    $isMine ? 'Dein privates Equipment' : 'Privat: ' . ($owner && $owner->exists() ? $owner->getDisplayName() : 'Unbekannt'),
                    $owner && $owner->exists() ? $owner->RenderProfileImage() : null,
                    $isMine
                );
            } else {
                $org = $item->Organization();
                $key = $group('org:' . (int) $item->OrganizationID, (string) $org->Title, $org->RenderLogo(40), false);
            }
            $groups[$key]['Items'][] = $this->formatContentItem($item, $member);
        }

        foreach (Room::get()->filter(['StorageLocationID' => 0, 'OrganizationID' => $orgIDs ?: [0]]) as $room) {
            if (!$room->isViewableBy($member)) {
                continue;
            }
            $org = $room->Organization();
            $key = $group('org:' . (int) $room->OrganizationID, (string) $org->Title, $org->RenderLogo(40), false);
            $groups[$key]['Rooms'][] = $this->formatContentRoom($room, $member);
        }

        $groups = array_values($groups);
        usort($groups, fn ($a, $b) => [!$a['IsMine'], mb_strtolower($a['Title'])] <=> [!$b['IsMine'], mb_strtolower($b['Title'])]);
        return $groups;
    }

    /**
     * Übernimmt Name, Beschreibung, übergeordneten Lagerpunkt, Art und Zusatzfelder.
     * Der Besitzer wird nur beim Anlegen gesetzt (siehe store()).
     */
    private function applyBody(StorageLocation $location, array $body, Member $member): ?string
    {
        if (array_key_exists('Title', $body)) {
            $location->Title = trim((string) $body['Title']);
        }
        if (array_key_exists('Description', $body)) {
            $location->Description = (string) $body['Description'];
        }

        if (array_key_exists('ParentID', $body)) {
            $parentID = (int) $body['ParentID'];
            if ($parentID && $parentID !== (int) $location->ParentID) {
                $parent = StorageLocation::get()->byID($parentID);
                if (!$parent || !$parent->isViewableBy($member)) {
                    return 'Übergeordneter Lagerpunkt nicht gefunden';
                }
            }
            $location->ParentID = $parentID;
        }

        if (array_key_exists('TypeID', $body)) {
            $typeID = (int) $body['TypeID'];
            if ($typeID) {
                // Org-Lagerpunkte nutzen die Arten ihrer Organisation, private die aller Organisationen des Besitzers
                $typeOrgIDs = $location->isPrivate() ? $this->inventoryOrgIDs($member) : [(int) $location->OrganizationID];
                $type = InventoryItemType::get()->filter([
                    'ID'             => $typeID,
                    'OrganizationID' => $typeOrgIDs ?: [0],
                    'AppliesTo'      => 'storage',
                ])->first();
                if (!$type) {
                    return 'Ungültige Art';
                }
            }
            $location->TypeID = $typeID;
        }

        $type = $location->Type();
        if (isset($body['Values']) && is_array($body['Values']) && $type && $type->exists()) {
            $location->applyMetaValues($type, $body['Values']);
        }

        return null;
    }

    /**
     * Setzt die Freigaben (`SharedWithIDs`) — nur für Organisationen, in denen der
     * Nutzer selbst Mitglied ist (bei Org-Lagerpunkten ohne die besitzende).
     * Freigaben für andere Organisationen, die jemand anderes gesetzt hat, bleiben erhalten.
     */
    private function applySharing(StorageLocation $location, array $body, Member $member): void
    {
        if (!array_key_exists('SharedWithIDs', $body)) {
            return;
        }
        $allowed = array_values(array_diff(array_map('intval', $this->inventoryOrgIDs($member)), [(int) $location->OrganizationID]));
        $requested = array_intersect(array_map('intval', (array) $body['SharedWithIDs']), $allowed);
        $foreign = array_diff(array_map('intval', $location->SharedWith()->column('ID')), $allowed);
        $location->SharedWith()->setByIDList(array_values(array_unique([...$requested, ...$foreign])));
    }

    private function writeWithValidation(StorageLocation $location): ?string
    {
        try {
            $location->write();
            return null;
        } catch (ValidationException $e) {
            $messages = array_map(fn ($m) => $m['message'], $e->getResult()->getMessages());
            return implode(' ', $messages) ?: 'Ungültige Eingabe';
        }
    }

    /** GET /api/v1/storage */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = $this->inventoryOrgIDs($member);
        $locations = $this->visibleLocations($member, $orgIDs);
        [$items, $rooms] = $this->contentsByLocation($member, array_map(fn (StorageLocation $l) => $l->ID, $locations));

        $data = [];
        foreach ($locations as $location) {
            $data[] = $this->formatLocation($location, $member, $items[$location->ID] ?? [], $rooms[$location->ID] ?? []);
        }

        $orgData = [];
        foreach (Organization::get()->filter('ID', $orgIDs ?: [0])->sort('Title') as $org) {
            $orgData[] = [
                'ID'        => $org->ID,
                'Title'     => $org->Title,
                'LogoURL'   => $org->RenderLogo(40),
                'CanCreate' => $member->hasOrgPermission($org, OrgPermissions::STORAGE_CREATE),
            ];
        }

        return $this->jsonResponse([
            'locations'     => $data,
            'unassigned'    => $this->unassignedGroups($member, $orgIDs),
            'organizations' => $orgData,
        ]);
    }

    /** POST /api/v1/storage/store */
    public function store(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        $location = StorageLocation::create();

        if (($body['OwnerType'] ?? 'organization') === 'member') {
            // Private Lagerpunkte gehören der Person, die sie anlegt (muss in einer Org mit Inventar sein)
            if (!$this->inventoryOrgIDs($member)) {
                return $this->errorResponse('Du bist in keiner Organisation mit Inventar', 403);
            }
            $location->OwnerType = 'member';
            $location->OwnerMemberID = $member->ID;
        } else {
            $orgID = (int) ($body['OrganizationID'] ?? 0);
            $org = $orgID ? Organization::get()->byID($orgID) : null;
            if (!$org || !$org->exists() || !$org->EnableInventory) {
                return $this->errorResponse('Organisation nicht gefunden', 404);
            }
            if (!$member->hasOrgPermission($org, OrgPermissions::STORAGE_CREATE)) {
                return $this->errorResponse('Keine Berechtigung, in dieser Organisation Lagerpunkte anzulegen', 403);
            }
            $location->OwnerType = 'organization';
            $location->OrganizationID = $org->ID;
        }

        if ($error = $this->applyBody($location, $body, $member)) {
            return $this->errorResponse($error, 400);
        }
        if ($error = $this->writeWithValidation($location)) {
            return $this->errorResponse($error, 400);
        }
        $this->applySharing($location, $body, $member);

        return $this->successResponse(['location' => $this->formatLocationResponse($location, $member)], 'Lagerpunkt angelegt');
    }

    /** PUT /api/v1/storage/update/$ID */
    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $location = StorageLocation::get()->byID((int) $request->param('ID'));
        if (!$location || !$location->exists()) {
            return $this->errorResponse('Lagerpunkt nicht gefunden', 404);
        }
        if (!$location->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        if ($error = $this->applyBody($location, $body, $member)) {
            return $this->errorResponse($error, 400);
        }
        if ($error = $this->writeWithValidation($location)) {
            return $this->errorResponse($error, 400);
        }
        $this->applySharing($location, $body, $member);

        return $this->successResponse(['location' => $this->formatLocationResponse($location, $member)], 'Lagerpunkt gespeichert');
    }

    /**
     * POST /api/v1/storage/move/$ID { ParentID, OrderedIDs } — Drag & Drop im Lager-Tab:
     * verschiebt den Lagerpunkt in ParentID (0 = oberste Ebene) und übernimmt die
     * Reihenfolge der (für den Nutzer sichtbaren) Lagerpunkte dieser Ebene aus OrderedIDs.
     * Verschieben darf, wer den Lagerpunkt bearbeiten darf; die Reihenfolge der
     * übrigen Lagerpunkte der Ebene ändert sich dabei nur in der Sortierung.
     */
    public function move(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $location = StorageLocation::get()->byID((int) $request->param('ID'));
        if (!$location || !$location->exists()) {
            return $this->errorResponse('Lagerpunkt nicht gefunden', 404);
        }
        if (!$location->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $orderedIDs = array_values(array_unique(array_map('intval', (array) ($body['OrderedIDs'] ?? []))));
        if (!in_array((int) $location->ID, $orderedIDs, true)) {
            return $this->errorResponse('Ungültige Reihenfolge', 400);
        }

        if ($error = $this->applyBody($location, ['ParentID' => $body['ParentID'] ?? 0], $member)) {
            return $this->errorResponse($error, 400);
        }
        $location->keepSortOrder = true;
        $location->SortOrder = array_search((int) $location->ID, $orderedIDs, true);
        if ($error = $this->writeWithValidation($location)) {
            return $this->errorResponse($error, 400);
        }

        // Geschwister derselben Ebene neu nummerieren (nur sichtbare, nur die Sortierung)
        $siblings = StorageLocation::get()->filter([
            'ID'       => $orderedIDs,
            'ParentID' => (int) $location->ParentID,
        ])->exclude('ID', $location->ID);
        foreach ($siblings as $sibling) {
            $index = array_search((int) $sibling->ID, $orderedIDs, true);
            if ($index !== false && (int) $sibling->SortOrder !== $index && $sibling->isViewableBy($member)) {
                $sibling->keepSortOrder = true;
                $sibling->SortOrder = $index;
                $sibling->write();
            }
        }

        return $this->successResponse([], 'Lagerpunkt verschoben');
    }

    /**
     * POST /api/v1/storage/place { Kind: 'item'|'room', ID, StorageLocationID } — Objekt,
     * Fahrzeug oder Raum per Drag & Drop einsortieren (StorageLocationID 0 = "Nicht
     * einsortiert"). Erlaubt, wer das Objekt bzw. den Raum bearbeiten darf.
     */
    public function place(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        $record = ($body['Kind'] ?? '') === 'room'
            ? Room::get()->byID((int) ($body['ID'] ?? 0))
            : InventoryItem::get()->byID((int) ($body['ID'] ?? 0));
        if (!$record || !$record->exists()) {
            return $this->errorResponse('Nicht gefunden', 404);
        }
        if (!$record->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }
        if ($error = StorageLocation::assignTo($record, $body['StorageLocationID'] ?? 0, $member)) {
            return $this->errorResponse($error, 400);
        }
        $record->write();

        return $this->successResponse(['StorageLocation' => StorageLocation::apiSummary((int) $record->StorageLocationID)], 'Einsortiert');
    }

    /**
     * POST /api/v1/storage/share/$ID { Revoke? } — öffentlichen Teilen-Link (z.B. fürs
     * NFC-Tag an einer Kiste) anlegen bzw. vorhandenen zurückgeben, oder mit Revoke deaktivieren.
     */
    public function share(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $location = StorageLocation::get()->byID((int) $request->param('ID'));
        if (!$location || !$location->exists()) {
            return $this->errorResponse('Lagerpunkt nicht gefunden', 404);
        }
        if (!$location->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        if (!empty($this->getJsonBody()['Revoke'])) {
            $location->revokeShareToken();
            return $this->successResponse(['url' => null], 'Link deaktiviert');
        }

        $location->ensureShareToken();
        return $this->successResponse(['url' => $this->shareURL($location)], 'Link erstellt');
    }

    /**
     * GET /api/v1/storage/public/$Token — Seite hinter dem Teilen-Link/NFC-Tag, auch ohne
     * Anmeldung. Wer den Lagerpunkt sehen darf, bekommt alles wie im Lager-Tab; sonst
     * Name, Art, Besitzer, Beschreibung, die als öffentlich markierten Felder und den
     * Inhalt (Objekte, Räume, enthaltene Lagerpunkte — jeweils mit Link auf ihre
     * eigene öffentliche Seite, sofern sie einen Teilen-Link haben).
     */
    public function public(HTTPRequest $request): HTTPResponse
    {
        $token = (string) $request->param('ID');
        $location = $token !== '' ? StorageLocation::get()->filter('ShareToken', $token)->first() : null;
        if (!$location) {
            return $this->errorResponse('Dieser Link ist ungültig oder wurde deaktiviert', 404);
        }

        $type = $location->Type();
        $member = $this->requireAuth();
        if ($member && $location->isViewableBy($member)) {
            $data = $this->formatLocationResponse($location, $member);
            $data['Path'] = array_slice(StorageLocation::pathTitles((int) $location->ID), 0, -1);
            $data['Children'] = [];
            $children = $location->Children()->toArray();
            [$childItems, $childRooms] = $this->contentsByLocation($member, array_map(fn (StorageLocation $c) => $c->ID, $children));
            foreach ($children as $child) {
                if ($child->isViewableBy($member)) {
                    $childType = $child->Type();
                    $data['Children'][] = [
                        'ID'    => $child->ID,
                        'Title' => $child->Title,
                        'Type'  => $childType && $childType->exists() ? $childType->Title : null,
                        // Direkter Inhalt des enthaltenen Lagerpunkts (ohne weitere Ebenen)
                        'Count' => count($childItems[$child->ID] ?? []) + count($childRooms[$child->ID] ?? []),
                    ];
                }
            }
            return $this->jsonResponse(['isMember' => true, 'location' => $data]);
        }

        [$items, $rooms, $children] = $this->publicContents($location);
        $values = $location->getMetaValueMap();
        $fields = [];
        foreach ($type && $type->exists() ? $type->getFieldsForApi() : [] as $field) {
            if ($field['IsPublic'] && isset($values[$field['ID']])) {
                $field['Value'] = $values[$field['ID']];
                $fields[] = $field;
            }
        }
        $owner = $location->getOwnerLabel();

        return $this->jsonResponse([
            'isMember' => false,
            'location' => [
                'Title'       => $location->Title,
                'Description' => $location->Description,
                'Type'        => $type && $type->exists() ? $type->Title : null,
                'Owner'       => $owner,
                'Fields'      => $fields,
                'Items'       => $items,
                'Rooms'       => $rooms,
                'Children'    => $children,
            ],
        ]);
    }

    /**
     * Inhalt eines Lagerpunkts für die öffentliche Seite (ohne Anmeldung): alles, was
     * direkt darin lagert (ohne ausgemusterte Objekte), und die enthaltenen Lagerpunkte.
     * `PublicPath` (App-Route) nur, wenn das Gegenüber selbst einen Teilen-Link hat.
     *
     * @return array{0: array, 1: array, 2: array}
     */
    private function publicContents(StorageLocation $location): array
    {
        $items = [];
        foreach ($location->Items()->exclude('Status', 'retired') as $item) {
            $image = $item->Images()->first();
            $items[] = [
                'ID'              => $item->ID,
                'Title'           => $item->Title,
                'InventoryNumber' => $item->InventoryNumber,
                'Kind'            => $item->Kind ?: 'item',
                'Status'          => $item->Status,
                'StatusLabel'     => InventoryItem::STATUS_LABELS[$item->Status] ?? $item->Status,
                'Thumbnail'       => $image && $image->exists() ? $image->Fill(80, 80)->getURL() : null,
                'PublicPath'      => $item->ShareToken ? '/inventory/share/' . $item->ShareToken : null,
            ];
        }

        $rooms = [];
        foreach ($location->Rooms() as $room) {
            $rooms[] = [
                'ID'         => $room->ID,
                'Title'      => $room->Title,
                'PublicPath' => $room->ShareToken ? '/rooms/share/' . $room->ShareToken : null,
            ];
        }

        $children = [];
        foreach ($location->Children() as $child) {
            $type = $child->Type();
            $children[] = [
                'ID'         => $child->ID,
                'Title'      => $child->Title,
                'Type'       => $type && $type->exists() ? $type->Title : null,
                'Count'      => $child->Items()->exclude('Status', 'retired')->count() + $child->Rooms()->count(),
                'PublicPath' => $child->ShareToken ? '/storage/share/' . $child->ShareToken : null,
            ];
        }

        return [$items, $rooms, $children];
    }

    /**
     * DELETE /api/v1/storage/remove/$ID — der Inhalt (Lagerpunkte, Objekte, Räume)
     * rückt in den übergeordneten Lagerpunkt (siehe StorageLocation::onBeforeDelete()).
     */
    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $location = StorageLocation::get()->byID((int) $request->param('ID'));
        if (!$location || !$location->exists()) {
            return $this->errorResponse('Lagerpunkt nicht gefunden', 404);
        }
        if (!$location->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $location->delete();

        return $this->successResponse([], 'Lagerpunkt gelöscht');
    }
}
