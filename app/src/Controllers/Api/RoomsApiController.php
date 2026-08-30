<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Rooms\Room;
use App\Tasks\Task;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\RoomsApiController
 *
 */
class RoomsApiController extends ApiController
{
    private static $url_segment = 'api/v1/rooms';

    private static $allowed_actions = [
        'index',
        'detail',
        'attachableTasks',
        'store',
        'update',
        'remove',
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

    private function formatRoom(Room $room, Member $member): array
    {
        $org = $room->Organization();

        return [
            'ID'           => $room->ID,
            'Title'        => $room->Title,
            'Description'  => $room->Description,
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
            'DeadlineNice' => $task->Deadline ? $task->dbObject('Deadline')->Nice() : null,
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

        $tasks = [];
        foreach ($room->Tasks()->sort('Created DESC') as $task) {
            $tasks[] = $this->formatAttachedTask($task);
        }

        $data = $this->formatRoom($room, $member);
        $data['Tasks'] = $tasks;

        return $this->jsonResponse(['room' => $data]);
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
            $room->OrganizationID = $orgID;
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

        try {
            $room->delete();
            return $this->successResponse([], 'Raum gelöscht');
        } catch (\Exception $e) {
            error_log('RoomsApiController::remove error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Löschen des Raums', 500);
        }
    }
}
