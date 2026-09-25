<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Inventory\InventoryDamageReport;
use App\Inventory\InventoryItemType;
use App\Inventory\InventoryRental;
use App\Rooms\Room;
use App\Tasks\Task;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\RoomsApiController
 *
 */
class RoomsApiController extends ApiController
{
    use AttachmentUploads;

    private static $url_segment = 'api/v1/rooms';

    private static $allowed_actions = [
        'index',
        'detail',
        'attachableTasks',
        'store',
        'update',
        'remove',
        'uploadFiles',
        'removeFile',
        'share',
        'public',
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

    private function formatRoom(Room $room, Member $member): array
    {
        $org = $room->Organization();
        $firstImage = $room->Images()->first();

        return [
            'ID'           => $room->ID,
            'Title'        => $room->Title,
            'Description'  => $room->Description,
            'IsRentable'   => (bool) $room->IsRentable,
            'TypeID'       => (int) $room->TypeID ?: null,
            'Type'         => $room->Type()->exists() ? ['ID' => $room->TypeID, 'Title' => $room->Type()->Title] : null,
            'Fields'       => $room->Type()->exists() ? $room->Type()->getFieldsForApi() : [],
            // Werte der Zusatzfelder: { "<Feld-ID>": "<Wert>" }
            'Values'       => (object) $room->getMetaValueMap(),
            'Thumbnail'    => $firstImage && $firstImage->exists() ? $firstImage->Fill(160, 160)->getURL() : null,
            // Heute durch eine genehmigte/laufende Reservierung belegt
            'IsOccupied'   => $room->IsRentable && $room->Rentals()->filter([
                'Status'                     => InventoryRental::BLOCKING_STATUSES,
                'StartDate:LessThanOrEqual'  => date('Y-m-d'),
                'EndDate:GreaterThanOrEqual' => date('Y-m-d'),
            ])->exists(),
            'Organization' => $org && $org->exists() ? [
                'ID'    => $org->ID,
                'Title' => $org->Title,
            ] : null,
            'CanEdit'      => $room->isEditableBy($member),
            'CanDelete'    => $room->isDeletableBy($member),
        ];
    }

    /**
     * Leichtgewichtige Task-Darstellung für die Aufgabenliste im Raum-Modal —
     * bewusst ohne die rekursive Unteraufgaben-Formatierung aus TasksApiController.
     */
    private function formatAttachedTask(Task $task): array
    {
        $owner = $task->Owner();

        return [
            'ID'           => $task->ID,
            'Hash'         => $task->Hash,
            'Title'        => $task->Title,
            'State'        => $task->State ?: 'open',
            'DeadlineNice' => $task->Deadline ? $task->dbObject('Deadline')->Date() : null,
            'Owner'        => ($owner && $owner->exists()) ? [
                'ID'     => $owner->ID,
                'Name'   => $owner->getDisplayName(),
                'Avatar' => $owner->RenderProfileImage(),
            ] : null,
        ];
    }

    /** GET /api/v1/rooms */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = $member->getOrganizationIDs();

        $rooms = Room::get()->filter(['OrganizationID' => $orgIDs ?: [0]])->sort('Title ASC');

        if ($orgID = (int) $request->getVar('organization')) {
            $rooms = $rooms->filter('OrganizationID', $orgID);
        }

        if ($search = $request->getVar('search')) {
            $rooms = $rooms->filter('Title:PartialMatch', $search);
        }

        $data = [];
        foreach ($rooms as $room) {
            $data[] = $this->formatRoom($room, $member);
        }

        $orgData = [];
        foreach ($orgIDs as $oid) {
            $org = Organization::get()->byID($oid);
            if ($org) {
                $orgData[] = ['ID' => $org->ID, 'Title' => $org->Title];
            }
        }

        return $this->jsonResponse(['rooms' => $data, 'organizations' => $orgData]);
    }

    /** GET /api/v1/rooms/detail/$ID */
    public function detail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $id   = (int) $request->param('ID');
        $room = $id ? Room::get()->byID($id) : null;

        if (!$room || !$room->exists()) {
            return $this->errorResponse('Raum nicht gefunden', 404);
        }

        if (!$room->isViewableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        return $this->jsonResponse(['room' => $this->formatRoomDetail($room, $member)]);
    }

    /** Vollständige Raum-Daten (Detailansicht, auch für Mitglieder auf der öffentlichen Seite) */
    private function formatRoomDetail(Room $room, Member $member): array
    {
        $tasks = [];
        foreach ($room->Tasks()->sort('Created DESC') as $task) {
            $tasks[] = $this->formatAttachedTask($task);
        }

        $data = $this->formatRoom($room, $member);
        $data['Tasks'] = $tasks;

        // Anstehende und laufende Reservierungen (siehe Inventar-Totem)
        $data['Rentals'] = [];
        $rentals = $room->Rentals()
            ->filter(['Status' => ['requested', 'approved', 'handed_over'], 'EndDate:GreaterThanOrEqual' => date('Y-m-d')])
            ->sort('StartDate ASC');
        foreach ($rentals as $rental) {
            $renter = $rental->Member();
            $data['Rentals'][] = [
                'ID'          => $rental->ID,
                'StartDate'   => $rental->StartDate,
                'EndDate'     => $rental->EndDate,
                'Status'      => $rental->Status,
                'StatusLabel' => InventoryRental::STATUS_LABELS[$rental->Status] ?? $rental->Status,
                'Member'      => $renter && $renter->exists() ? ['ID' => $renter->ID, 'Name' => $renter->getDisplayName()] : null,
            ];
        }

        $org = $room->Organization();
        $data['CanReserve'] = $room->IsRentable
            && $org && $org->exists() && $org->EnableInventory
            && $member->hasOrgPermission($org, OrgPermissions::INVENTORY_REQUEST_RENTAL);

        $data += $this->formatAttachments($room);

        // Alle Schadensmeldungen (auch behobene), neueste zuerst
        $data['Damages'] = array_map(
            fn (InventoryDamageReport $report) => $report->toApi($member),
            $room->DamageReports()->limit(50)->toArray()
        );

        // Öffentlicher Teilen-Link — nur für Bearbeitende sichtbar
        $data['ShareURL'] = $room->ShareToken && $room->isEditableBy($member) ? $this->shareURL($room) : null;

        return $data;
    }

    private function shareURL(Room $room): string
    {
        return Director::absoluteURL('/app/rooms/share/' . $room->ShareToken);
    }

    /**
     * POST /api/v1/rooms/share/$ID { Revoke? } — öffentlichen Teilen-Link anlegen
     * (bzw. vorhandenen zurückgeben) oder mit Revoke deaktivieren.
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

        $room = Room::get()->byID((int) $request->param('ID'));
        if (!$room || !$room->exists()) {
            return $this->errorResponse('Raum nicht gefunden', 404);
        }
        if (!$room->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        if (!empty($this->getJsonBody()['Revoke'])) {
            $room->revokeShareToken();
            return $this->successResponse(['url' => null], 'Link deaktiviert');
        }

        $room->ensureShareToken();
        return $this->successResponse(['url' => $this->shareURL($room)], 'Link erstellt');
    }

    /**
     * GET /api/v1/rooms/public/$Token — öffentliche Ansicht über den Teilen-Link,
     * auch ohne Anmeldung. Wer angemeldet ist und den Raum sehen darf, bekommt die
     * vollständigen Daten (Bearbeiten/Reservieren im Frontend); sonst nur Stammdaten,
     * Bilder und die als öffentlich markierten Felder der Raum-Art.
     */
    public function public(HTTPRequest $request): HTTPResponse
    {
        $token = (string) $request->param('ID');
        $room = $token !== '' ? Room::get()->filter('ShareToken', $token)->first() : null;
        if (!$room) {
            return $this->errorResponse('Dieser Link ist ungültig oder wurde deaktiviert', 404);
        }

        $member = $this->requireAuth();
        if ($member && $room->isViewableBy($member)) {
            return $this->jsonResponse(['isMember' => true, 'room' => $this->formatRoomDetail($room, $member)]);
        }

        $type = $room->Type();
        $values = $room->getMetaValueMap();
        $fields = [];
        foreach ($type->exists() ? $type->getFieldsForApi() : [] as $field) {
            if ($field['IsPublic'] && isset($values[$field['ID']])) {
                $field['Value'] = $values[$field['ID']];
                $fields[] = $field;
            }
        }
        $org = $room->Organization();

        return $this->jsonResponse([
            'isMember' => false,
            'room'     => [
                'Title'        => $room->Title,
                'Description'  => $room->Description,
                'Type'         => $type->exists() ? $type->Title : null,
                'Organization' => $org && $org->exists() ? $org->Title : null,
                'Fields'       => $fields,
                'Images'       => $this->formatImages($room->Images()),
            ],
        ]);
    }

    /** GET /api/v1/rooms/attachableTasks/?organization=$ID */
    public function attachableTasks(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgID = (int) $request->getVar('organization');
        if (!$orgID || !in_array($orgID, $member->getOrganizationIDs(), true)) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        $tasks = Task::get()->filter([
            'OrganizationID' => $orgID,
            'ParentID'       => 0,
        ])->sort('Title ASC');

        $data = [];
        foreach ($tasks as $task) {
            $data[] = ['ID' => $task->ID, 'Hash' => $task->Hash, 'Title' => $task->Title];
        }

        return $this->jsonResponse(['tasks' => $data]);
    }

    /** POST /api/v1/rooms/store */
    public function store(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body  = $this->getJsonBody();
        $title = trim($body['Title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $orgID = (int) ($body['OrganizationID'] ?? 0);
        $org   = $orgID ? Organization::get()->byID($orgID) : null;
        if (!$org || !$org->exists()) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        if (!$member->hasOrgPermission($org, OrgPermissions::ROOMS_CREATE)) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        try {
            $room                = Room::create();
            $room->Title         = $title;
            $room->Description   = $body['Description'] ?? '';
            $room->IsRentable    = !empty($body['IsRentable']);
            $room->OrganizationID = $orgID;
            if ($error = $this->applyTypeAndValues($room, $body)) {
                return $this->errorResponse($error, 400);
            }
            $room->write();

            if (!empty($body['TaskIDs']) && is_array($body['TaskIDs'])) {
                foreach ($body['TaskIDs'] as $tid) {
                    $room->Tasks()->add((int) $tid);
                }
            }

            return $this->successResponse(['room' => $this->formatRoom($room, $member)], 'Raum erstellt');
        } catch (\Exception $e) {
            error_log('RoomsApiController::store error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Erstellen des Raums', 500);
        }
    }

    /** PUT /api/v1/rooms/update/$ID */
    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $id   = (int) $request->param('ID');
        $room = $id ? Room::get()->byID($id) : null;
        if (!$room || !$room->exists()) {
            return $this->errorResponse('Raum nicht gefunden', 404);
        }

        if (!$room->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();

        try {
            if (isset($body['Title'])) {
                $room->Title = trim($body['Title']);
            }
            if (isset($body['Description'])) {
                $room->Description = $body['Description'];
            }
            if (array_key_exists('IsRentable', $body)) {
                $room->IsRentable = (bool) $body['IsRentable'];
            }
            if ($error = $this->applyTypeAndValues($room, $body)) {
                return $this->errorResponse($error, 400);
            }
            $room->write();

            if (isset($body['TaskIDs']) && is_array($body['TaskIDs'])) {
                $room->Tasks()->removeAll();
                foreach ($body['TaskIDs'] as $tid) {
                    $room->Tasks()->add((int) $tid);
                }
            }

            return $this->successResponse(['room' => $this->formatRoom($room, $member)], 'Raum aktualisiert');
        } catch (\Exception $e) {
            error_log('RoomsApiController::update error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Aktualisieren des Raums', 500);
        }
    }

    /** DELETE /api/v1/rooms/remove/$ID */
    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $id   = (int) $request->param('ID');
        $room = $id ? Room::get()->byID($id) : null;
        if (!$room || !$room->exists()) {
            return $this->errorResponse('Raum nicht gefunden', 404);
        }

        if (!$room->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        if ($room->Rentals()->filter('Status', InventoryRental::BLOCKING_STATUSES)->exists()) {
            return $this->errorResponse('Der Raum ist für eine Reservierung genehmigt oder gerade belegt und kann nicht gelöscht werden', 400);
        }

        try {
            $room->Rentals()->removeAll();
            $room->delete();
            return $this->successResponse([], 'Raum gelöscht');
        } catch (\Exception $e) {
            error_log('RoomsApiController::remove error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Löschen des Raums', 500);
        }
    }

    /**
     * Art (nur Raum-Arten der Organisation des Raums, 0 = keine) und Werte ihrer
     * Zusatzfelder ({ Values: { "<Feld-ID>": "<Wert>" } }). Gibt einen Fehlertext zurück oder null.
     */
    private function applyTypeAndValues(Room $room, array $body): ?string
    {
        if (array_key_exists('TypeID', $body)) {
            $typeID = (int) $body['TypeID'];
            if ($typeID) {
                $type = InventoryItemType::get()->filter([
                    'ID'             => $typeID,
                    'OrganizationID' => $room->OrganizationID,
                    'AppliesTo'      => 'room',
                ])->first();
                if (!$type) {
                    return 'Ungültige Art';
                }
            }
            $room->TypeID = $typeID;
        }

        $type = $room->Type();
        if (isset($body['Values']) && is_array($body['Values']) && $type->exists()) {
            $room->applyMetaValues($type, $body['Values']);
        }
        return null;
    }

    /** Ablageordner für Bilder/Dokumente eines Raums */
    private function attachmentFolder(Room $room): string
    {
        $org = $room->Organization();
        return 'Rooms/' . $this->attachmentSlug($org && $org->exists() ? $org->Title : null) . '/' . $room->ID . '-' . $this->attachmentSlug($room->Title);
    }

    /** POST /api/v1/rooms/uploadFiles/$ID (multipart: images[], documents[]) */
    public function uploadFiles(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $room = Room::get()->byID((int) $request->param('ID'));
        if (!$room || !$room->exists()) {
            return $this->errorResponse('Raum nicht gefunden', 404);
        }
        if (!$room->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $errors = $this->attachUploadedFiles([$room], $this->attachmentFolder($room));
        $data = $this->formatRoom($room, $member) + $this->formatAttachments($room);

        if ($errors) {
            return $this->jsonResponse([
                'success' => false,
                'error'   => implode(' ', $errors),
                'data'    => ['room' => $data],
            ], 400);
        }

        return $this->successResponse(['room' => $data], 'Dateien hochgeladen');
    }

    /** POST /api/v1/rooms/removeFile/$ID { FileID } */
    public function removeFile(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $room = Room::get()->byID((int) $request->param('ID'));
        if (!$room || !$room->exists()) {
            return $this->errorResponse('Raum nicht gefunden', 404);
        }
        if (!$room->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $fileID = (int) ($this->getJsonBody()['FileID'] ?? 0);
        $file = $room->Images()->byID($fileID) ?: $room->Documents()->byID($fileID);
        if (!$file) {
            return $this->errorResponse('Datei nicht gefunden', 404);
        }

        $room->Images()->removeByID($file->ID);
        $room->Documents()->removeByID($file->ID);
        $file->deleteFile();
        $file->doArchive();

        return $this->successResponse(['room' => $this->formatRoom($room, $member) + $this->formatAttachments($room)], 'Datei entfernt');
    }
}
