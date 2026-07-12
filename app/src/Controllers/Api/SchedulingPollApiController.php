<?php

namespace App\Controllers\Api;

use App\Calendar\Appointment;
use App\Calendar\SchedulingPoll;
use App\Calendar\SchedulingPollOption;
use App\Calendar\SchedulingPollOptionParticipation;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Scheduling Poll (Terminfindung) API Controller
 *
 */
class SchedulingPollApiController extends ApiController
{
    private static $url_segment = 'api/v1/scheduling-poll';

    private static $allowed_actions = [
        'poll',
        'pollOptionParticipation',
        'finalize',
    ];

    /**
     * Create/update/delete a scheduling poll.
     * POST   /api/v1/scheduling-poll/poll
     * PUT    /api/v1/scheduling-poll/poll
     * DELETE /api/v1/scheduling-poll/poll?id=X
     * Body: { title, description, location, organizationIds[], invitedMemberIds[], options: [{id?, dateStart, dateEnd, timeStart, timeEnd, allDay}] }
     */
    public function poll(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $body = json_decode($request->getBody(), true) ?? [];

        // DELETE
        if ($request->httpMethod() === 'DELETE') {
            $id = (int) ($request->getVar('id') ?? 0);
            $poll = SchedulingPoll::get()->byID($id);
            if (!$poll) {
                return $this->errorResponse('Nicht gefunden', 404);
            }
            $orgIDs = $poll->Organisations()->column('ID');
            $hasPermission = $this->hasPermissionInAnyOrg($member, $orgIDs, OrgPermissions::CALENDAR_DELETE);
            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }
            $this->deletePollCascade($poll);
            return $this->successResponse([], 'Terminfindung gelöscht');
        }

        // PUT (update)
        if ($request->httpMethod() === 'PUT') {
            $id = (int) ($body['id'] ?? 0);
            $poll = SchedulingPoll::get()->byID($id);
            if (!$poll) {
                return $this->errorResponse('Nicht gefunden', 404);
            }
            $pollOrgIDs = $poll->Organisations()->column('ID');
            $hasPermission = $this->hasPermissionInAnyOrg($member, $pollOrgIDs, OrgPermissions::CALENDAR_MANAGE);
            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $options = $body['options'] ?? [];
            if (count($options) < 2) {
                return $this->errorResponse('Mindestens 2 Terminoptionen erforderlich', 400);
            }
            foreach ($options as $opt) {
                if (empty($opt['dateStart'])) {
                    return $this->errorResponse('Jede Terminoption benötigt ein Startdatum', 400);
                }
            }

            $poll->Title       = trim($body['title'] ?? '') ?: $poll->Title;
            $poll->Description = $body['description'] ?? '';
            $poll->Location    = $body['location'] ?? '';
            $poll->write();

            $newOrgIDs = array_map('intval', $body['organizationIds'] ?? []);
            if (!empty($newOrgIDs)) {
                $poll->Organisations()->setByIDList($newOrgIDs);
            }

            $effectiveOrgIDs = !empty($newOrgIDs) ? $newOrgIDs : $pollOrgIDs;
            $validMemberIDs = OrganizationMembership::get()->filter([
                'OrganizationID' => $effectiveOrgIDs,
                'Role'           => 'member',
            ])->column('MemberID');
            $invitedIDs = array_values(array_intersect(
                array_map('intval', $body['invitedMemberIds'] ?? []),
                $validMemberIDs
            ));
            $poll->InvitedMembers()->setByIDList($invitedIDs);

            // Optionen abgleichen: bestehende aktualisieren, neue anlegen, entfernte löschen
            $keepIDs = [];
            foreach ($options as $opt) {
                $allDay = !empty($opt['allDay']);
                $optID = (int) ($opt['id'] ?? 0);
                $option = $optID ? $poll->Options()->byID($optID) : null;
                if (!$option) {
                    $option = SchedulingPollOption::create();
                    $option->ParentID = $poll->ID;
                }
                $option->DateStart = $opt['dateStart'];
                $option->DateEnd   = $opt['dateEnd'] ?: $opt['dateStart'];
                $option->TimeStart = $allDay ? null : ($opt['timeStart'] ?: null);
                $option->TimeEnd   = $allDay ? null : ($opt['timeEnd'] ?: null);
                $option->AllDay    = $allDay;
                $option->write();
                $keepIDs[] = $option->ID;
            }
            foreach ($poll->Options() as $existingOption) {
                if (!in_array($existingOption->ID, $keepIDs, true)) {
                    $existingOption->OptionParticipations()->removeAll();
                    $existingOption->delete();
                }
            }

            return $this->successResponse(['ID' => $poll->ID], 'Terminfindung aktualisiert');
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $orgIDs = array_map('intval', $body['organizationIds'] ?? []);
        if (empty($orgIDs)) {
            return $this->errorResponse('Mindestens eine Organisation muss gewählt werden', 400);
        }

        $hasPermission = $this->hasPermissionInAnyOrg($member, $orgIDs, OrgPermissions::CALENDAR_MANAGE);
        if (!$hasPermission) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $title = trim($body['title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $options = $body['options'] ?? [];
        if (count($options) < 2) {
            return $this->errorResponse('Mindestens 2 Terminoptionen erforderlich', 400);
        }
        foreach ($options as $opt) {
            if (empty($opt['dateStart'])) {
                return $this->errorResponse('Jede Terminoption benötigt ein Startdatum', 400);
            }
        }

        $validMemberIDs = OrganizationMembership::get()->filter([
            'OrganizationID' => $orgIDs,
            'Role'           => 'member',
        ])->column('MemberID');
        $invitedIDs = array_values(array_intersect(
            array_map('intval', $body['invitedMemberIds'] ?? []),
            $validMemberIDs
        ));

        $poll = SchedulingPoll::create();
        $poll->Title       = $title;
        $poll->Description = $body['description'] ?? '';
        $poll->Location    = $body['location'] ?? '';
        $poll->Status      = 'Open';
        $poll->write();

        $poll->Organisations()->addMany($orgIDs);
        $poll->InvitedMembers()->addMany($invitedIDs);

        foreach ($options as $opt) {
            $allDay = !empty($opt['allDay']);
            $option = SchedulingPollOption::create();
            $option->DateStart = $opt['dateStart'];
            $option->DateEnd   = $opt['dateEnd'] ?: $opt['dateStart'];
            $option->TimeStart = $allDay ? null : ($opt['timeStart'] ?: null);
            $option->TimeEnd   = $allDay ? null : ($opt['timeEnd'] ?: null);
            $option->AllDay    = $allDay;
            $option->ParentID  = $poll->ID;
            $option->write();
        }

        return $this->successResponse(['ID' => $poll->ID], 'Terminfindung erstellt');
    }

    /**
     * Vote on a single poll option.
     * POST /api/v1/scheduling-poll/pollOptionParticipation/:optionId
     * Body: { response: 'Accept'|'Maybe'|'Decline' }
     */
    public function pollOptionParticipation(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $optionID = $request->param('ID');
        $option = SchedulingPollOption::get()->byID($optionID);
        if (!$option) {
            return $this->errorResponse('Terminoption nicht gefunden', 404);
        }

        $poll = $option->Parent();
        if (!$poll || !$poll->exists()) {
            return $this->errorResponse('Terminfindung nicht gefunden', 404);
        }

        $organizationIDs = $member->getOrganizationIDs();
        $sharedOrgs = $poll->Organisations()->filter('ID', $organizationIDs);
        if (!$sharedOrgs->exists()) {
            return $this->errorResponse('Access denied', 403);
        }

        $body = json_decode($request->getBody(), true);
        $type = $body['response'] ?? null;
        if (!$type || !in_array($type, ['Accept', 'Maybe', 'Decline'])) {
            return $this->errorResponse('Invalid participation type', 400);
        }

        $participation = $option->OptionParticipations()->filter(['MemberID' => $member->ID])->first();
        if (!$participation) {
            $participation = SchedulingPollOptionParticipation::create();
            $participation->ParentID = $option->ID;
            $participation->MemberID = $member->ID;
        }
        $participation->Type = $type;
        $participation->write();

        return $this->successResponse([
            'ID'   => $participation->ID,
            'Type' => $participation->Type,
        ], 'Teilnahme aktualisiert');
    }

    /**
     * Finalize a poll: pick the winning option, create a real Appointment from it,
     * and delete the poll (all options + votes) entirely.
     * POST /api/v1/scheduling-poll/finalize/:pollId
     * Body: { optionId }
     */
    public function finalize(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $pollID = (int) $request->param('ID');
        $poll = SchedulingPoll::get()->byID($pollID);
        if (!$poll) {
            return $this->errorResponse('Terminfindung nicht gefunden', 404);
        }

        $orgIDs = $poll->Organisations()->column('ID');
        $hasPermission = $this->hasPermissionInAnyOrg($member, $orgIDs, OrgPermissions::CALENDAR_MANAGE);
        if (!$hasPermission) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = json_decode($request->getBody(), true) ?? [];
        $optionID = (int) ($body['optionId'] ?? 0);
        $option = $poll->Options()->byID($optionID);
        if (!$option) {
            return $this->errorResponse('Terminoption nicht gefunden', 404);
        }

        $appt = Appointment::create();
        $appt->Title       = $poll->Title;
        $appt->Description = $poll->Description;
        $appt->Location    = $poll->Location;
        $appt->DateStart   = $option->DateStart;
        $appt->DateEnd     = $option->DateEnd ?: $option->DateStart;
        $appt->TimeStart   = $option->AllDay ? null : $option->TimeStart;
        $appt->TimeEnd     = $option->AllDay ? null : $option->TimeEnd;
        $appt->AllDay      = $option->AllDay;
        $appt->Status      = 'Scheduled';
        $appt->write();

        $appt->Organisations()->addMany($orgIDs);
        $appt->InvitedMembers()->addMany($poll->InvitedMembers()->column('ID'));

        $this->deletePollCascade($poll);

        return $this->successResponse(['ID' => $appt->ID], 'Termin aus Terminfindung erstellt');
    }

    /**
     * Löscht eine Terminfindung inkl. aller Optionen, Teilnahmen und Relationen.
     */
    private function deletePollCascade(SchedulingPoll $poll): void
    {
        foreach ($poll->Options() as $option) {
            $option->OptionParticipations()->removeAll();
            $option->delete();
        }
        $poll->Organisations()->removeAll();
        $poll->InvitedMembers()->removeAll();
        $poll->delete();
    }
}
