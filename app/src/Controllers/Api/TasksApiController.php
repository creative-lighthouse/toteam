<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Rooms\Room;
use App\Tasks\Task;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\TasksApiController
 *
 */
class TasksApiController extends ApiController
{
    private static $url_segment = 'api/v1/tasks';

    private static $allowed_actions = [
        'index',
        'detail',
        'store',
        'update',
        'remove',
        'updateState',
        'orgMembers',
        'assignableMembers',
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

    private function formatMember(?Member $member): ?array
    {
        if (!$member || !$member->exists()) {
            return null;
        }
        return [
            'ID'       => $member->ID,
            'Name'     => $member->getDisplayName(),
            'Avatar'   => $member->RenderProfileImage(),
            'Username' => $member->Username ?: null,
        ];
    }

    /**
     * @param array<int, Task[]>|null $subtasksByParent Preloaded subtasks grouped by
     *     ParentID (see {@see loadSubtasksByParent}). When null, subtasks are queried
     *     individually for this task — only acceptable when formatting a single task.
     */
    private function formatTask(Task $task, Member $member, bool $withSubTasks = true, ?array $subtasksByParent = null): array
    {
        $org = $task->Organization();
        $owner = $task->Owner();
        $parent = $task->Parent();
        $supporters = [];
        foreach ($task->Supporters() as $s) {
            $supporters[] = $this->formatMember($s);
        }

        $rooms = [];
        foreach ($task->Rooms() as $room) {
            $rooms[] = ['ID' => $room->ID, 'Title' => $room->Title];
        }

        $subTasks = [];
        if ($withSubTasks) {
            $subtaskRecords = $subtasksByParent !== null
                ? ($subtasksByParent[$task->ID] ?? [])
                : Task::get()->filter('ParentID', $task->ID)->sort('Created ASC');

            foreach ($subtaskRecords as $sub) {
                $subTasks[] = $this->formatTask($sub, $member, false);
            }
        }

        return [
            'ID'             => $task->ID,
            'Hash'           => $task->Hash,
            'Title'          => $task->Title,
            'Description'    => $task->Description,
            'Deadline'       => $task->Deadline,
            'DeadlineNice'   => $task->Deadline ? $task->dbObject('Deadline')->Nice() : null,
            'State'          => $task->State ?: 'open',
            'ParentID'       => $task->ParentID ?: null,
            'Parent'         => ($parent && $parent->exists()) ? [
                'ID'    => $parent->ID,
                'Hash'  => $parent->Hash,
                'Title' => $parent->Title,
            ] : null,
            'Organization'   => $org && $org->exists() ? [
                'ID'      => $org->ID,
                'Title'   => $org->Title,
                'LogoURL' => $org->RenderLogo(40),
            ] : null,
            'Owner'          => $this->formatMember($owner->exists() ? $owner : null),
            'Supporters'     => $supporters,
            'Rooms'          => $rooms,
            'SubTasks'       => $subTasks,
            'CanEdit'        => $task->isEditableBy($member),
            'CanDelete'      => $task->isDeletableBy($member),
        ];
    }

    /**
     * Loads all subtasks for the given parent task IDs in a single query and
     * groups them by ParentID, avoiding one query per parent task.
     *
     * @param int[] $parentIDs
     * @return array<int, Task[]>
     */
    private function loadSubtasksByParent(array $parentIDs): array
    {
        $byParent = [];
        if (!$parentIDs) {
            return $byParent;
        }

        foreach (Task::get()->filter('ParentID', $parentIDs)->sort('Created ASC') as $sub) {
            $byParent[$sub->ParentID][] = $sub;
        }

        return $byParent;
    }

    /** GET /api/v1/tasks */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $orgIDs = $member->getOrganizationIDs();

            $tasks = Task::get()->filter([
                'ParentID'       => 0,
                'OrganizationID' => $orgIDs ?: [0],
            ])->sort('Created DESC');

            if ($orgID = (int) $request->getVar('organization')) {
                $tasks = $tasks->filter('OrganizationID', $orgID);
            }

            if ($search = $request->getVar('search')) {
                $tasks = $tasks->filterAny([
                    'Title:PartialMatch'       => $search,
                    'Description:PartialMatch' => $search,
                ]);
            }

            if ($state = $request->getVar('state')) {
                $tasks = $tasks->filter('State', $state);
            }

            if ($deadline = $request->getVar('deadline')) {
                $tasks = $tasks->filter('Deadline:LessThanOrEqual', $deadline . ' 23:59:59');
            }

            $subtasksByParent = $this->loadSubtasksByParent($tasks->column('ID'));

            $data = [];
            foreach ($tasks as $task) {
                $data[] = $this->formatTask($task, $member, true, $subtasksByParent);
            }

            $orgData = [];
            foreach ($orgIDs as $oid) {
                $org = Organization::get()->byID($oid);
                if ($org) {
                    $orgData[] = [
                        'ID'     => $org->ID,
                        'Title'  => $org->Title,
                        'LogoURL' => $org->RenderLogo(40),
                    ];
                }
            }

            return $this->jsonResponse(['tasks' => $data, 'organizations' => $orgData]);
        } catch (\Exception $e) {
            error_log('TasksApiController::index error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Laden der Aufgaben', 500);
        }
    }

    /** GET /api/v1/tasks/detail?hash=... */
    public function detail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $hash = $request->getVar('hash');
        $id   = (int) $request->param('ID');

        $task = $hash
            ? Task::get()->filter('Hash', $hash)->first()
            : ($id ? Task::get()->byID($id) : null);

        if (!$task || !$task->exists()) {
            return $this->errorResponse('Aufgabe nicht gefunden', 404);
        }

        if (!$task->isViewableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        return $this->jsonResponse(['task' => $this->formatTask($task, $member)]);
    }

    /** GET /api/v1/tasks/orgMembers/$ID */
    public function orgMembers(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgID = (int) $request->param('ID');
        if (!$orgID || !in_array($orgID, $member->getOrganizationIDs(), true)) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        $memberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $orgID,
            'Role'           => 'member',
        ]);

        $members = [];
        foreach ($memberships as $ms) {
            $formatted = $this->formatMember($ms->Member());
            if ($formatted) {
                $members[] = $formatted;
            }
        }

        return $this->jsonResponse(['members' => $members]);
    }

    /**
     * GET /api/v1/tasks/assignableMembers — every member across all of the current
     * user's organizations, for filtering the task list by assignee. Unlike the
     * task list itself, this includes members who aren't assigned to any task yet.
     */
    public function assignableMembers(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $memberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $member->getOrganizationIDs() ?: [0],
            'Role'           => 'member',
        ]);

        $members = [];
        foreach ($memberships as $ms) {
            $m = $ms->Member();
            if ($m && $m->exists()) {
                $members[$m->ID] = $this->formatMember($m);
            }
        }

        return $this->jsonResponse(['members' => array_values($members)]);
    }

    /**
     * Ob $ownerID ein aktives Mitglied der Organisation $orgID ist.
     */
    private function isOrgMember(int $ownerID, int $orgID): bool
    {
        return OrganizationMembership::get()->filter([
            'OrganizationID' => $orgID,
            'MemberID'       => $ownerID,
            'Role'           => 'member',
        ])->exists();
    }

    /** POST /api/v1/tasks/store */
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
        $title = trim($body['Title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $parentID = (int) ($body['ParentID'] ?? 0);
        $parentTask = $parentID ? Task::get()->byID($parentID) : null;
        if ($parentID && (!$parentTask || !$parentTask->exists() || !$parentTask->isViewableBy($member))) {
            return $this->errorResponse('Übergeordnete Aufgabe nicht gefunden', 404);
        }

        // Unteraufgaben übernehmen immer die Organisation der übergeordneten Aufgabe
        $orgID = $parentTask ? (int) $parentTask->OrganizationID : (int) ($body['OrganizationID'] ?? 0);
        if ($orgID) {
            $org = Organization::get()->byID($orgID);
            if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::TASKS_CREATE)) {
                return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
            }
        }

        $ownerID = (int) ($body['OwnerID'] ?? $member->ID);
        if ($orgID && !$this->isOrgMember($ownerID, $orgID)) {
            return $this->errorResponse('Der Verantwortliche muss Mitglied der Organisation sein', 400);
        }

        try {
            $task = Task::create();
            $task->Title          = $title;
            $task->Description    = $body['Description'] ?? '';
            $task->Deadline       = $body['Deadline'] ?? null;
            $task->State          = $body['State'] ?? 'open';
            $task->OrganizationID = $orgID;
            $task->OwnerID        = $ownerID;
            $task->ParentID       = $parentID;
            $task->write();

            if (!empty($body['SupporterIDs']) && is_array($body['SupporterIDs'])) {
                foreach ($body['SupporterIDs'] as $sid) {
                    $task->Supporters()->add((int) $sid);
                }
            }

            return $this->successResponse(['task' => $this->formatTask($task, $member)], 'Aufgabe erstellt');
        } catch (\Exception $e) {
            error_log('TasksApiController::create error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Erstellen der Aufgabe', 500);
        }
    }

    /** PUT /api/v1/tasks/update/$ID */
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
        $task = Task::get()->byID($id);
        if (!$task || !$task->exists()) {
            return $this->errorResponse('Aufgabe nicht gefunden', 404);
        }

        if (!$task->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();

        $resultingOrgID = isset($body['OrganizationID']) ? (int) $body['OrganizationID'] : $task->OrganizationID;
        $resultingOwnerID = isset($body['OwnerID']) ? (int) $body['OwnerID'] : $task->OwnerID;
        if ($resultingOrgID && $resultingOwnerID && !$this->isOrgMember($resultingOwnerID, $resultingOrgID)) {
            return $this->errorResponse('Der Verantwortliche muss Mitglied der Organisation sein', 400);
        }

        try {
            if (isset($body['Title']))          $task->Title          = trim($body['Title']);
            if (isset($body['Description']))    $task->Description    = $body['Description'];
            if (isset($body['Deadline']))       $task->Deadline       = $body['Deadline'];
            if (isset($body['State']))          $task->State          = $body['State'];
            if (isset($body['OwnerID']))        $task->OwnerID        = (int) $body['OwnerID'];
            if (isset($body['OrganizationID'])) $task->OrganizationID = (int) $body['OrganizationID'];
            $task->write();

            if (isset($body['SupporterIDs']) && is_array($body['SupporterIDs'])) {
                $task->Supporters()->removeAll();
                foreach ($body['SupporterIDs'] as $sid) {
                    $task->Supporters()->add((int) $sid);
                }
            }

            if (isset($body['RoomIDs']) && is_array($body['RoomIDs'])) {
                // Only attach rooms that actually belong to this task's organization
                $validRoomIDs = Room::get()
                    ->filter(['ID' => $body['RoomIDs'], 'OrganizationID' => $task->OrganizationID])
                    ->column('ID');

                $task->Rooms()->removeAll();
                foreach ($validRoomIDs as $rid) {
                    $task->Rooms()->add($rid);
                }
            }

            return $this->successResponse(['task' => $this->formatTask($task, $member)], 'Aufgabe aktualisiert');
        } catch (\Exception $e) {
            error_log('TasksApiController::update error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Aktualisieren der Aufgabe', 500);
        }
    }

    /** PUT /api/v1/tasks/updateState/$ID */
    public function updateState(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $id   = (int) $request->param('ID');
        $task = Task::get()->byID($id);
        if (!$task || !$task->exists()) {
            return $this->errorResponse('Aufgabe nicht gefunden', 404);
        }

        if (!$task->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body  = $this->getJsonBody();
        $state = $body['State'] ?? '';
        $allowed = ['open', 'in_progress', 'feedback', 'finished'];
        if (!in_array($state, $allowed, true)) {
            return $this->errorResponse('Ungültiger Status', 400);
        }

        $task->State = $state;
        $task->write();

        return $this->successResponse(['task' => $this->formatTask($task, $member)], 'Status aktualisiert');
    }

    /** DELETE /api/v1/tasks/remove/$ID */
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
        $task = Task::get()->byID($id);
        if (!$task || !$task->exists()) {
            return $this->errorResponse('Aufgabe nicht gefunden', 404);
        }

        if (!$task->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        // 'delete' = Unteraufgaben (rekursiv) mitlöschen, 'promote' = zu eigenständigen Aufgaben machen (Standard)
        $subtasksMode = $request->getVar('subtasks');
        $subtasksMode = in_array($subtasksMode, ['delete', 'promote'], true) ? $subtasksMode : 'promote';

        try {
            $subtasks = Task::get()->filter('ParentID', $task->ID);
            if ($subtasksMode === 'delete') {
                foreach ($subtasks as $sub) {
                    $this->deleteWithSubtasks($sub);
                }
            } else {
                foreach ($subtasks as $sub) {
                    $sub->ParentID = 0;
                    $sub->write();
                }
            }

            $task->delete();
            return $this->successResponse([], 'Aufgabe gelöscht');
        } catch (\Exception $e) {
            error_log('TasksApiController::delete error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Löschen der Aufgabe', 500);
        }
    }

    /**
     * Löscht eine Aufgabe inklusive aller (verschachtelten) Unteraufgaben.
     */
    private function deleteWithSubtasks(Task $task): void
    {
        foreach (Task::get()->filter('ParentID', $task->ID) as $sub) {
            $this->deleteWithSubtasks($sub);
        }
        $task->delete();
    }
}
