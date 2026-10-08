<?php

namespace App\Controllers\Api;

use App\Calendar\Absence;
use App\Calendar\Appointment;
use App\Calendar\AppointmentAgendaPoint;
use App\Calendar\AppointmentParticipation;
use App\Calendar\AppointmentParticipationStash;
use App\Calendar\AppointmentType;
use App\Calendar\SchedulingPollOption;
use App\Food\Meal;
use App\Food\MealEater;
use App\Food\MealProductOrder;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Skript\ScriptRoleAssignment;
use App\Teams\OrgEvent;
use App\Teams\OrgEventAgeGroup;
use App\Teams\OrgEventInterest;
use App\Teams\OrgEventType;
use App\Teams\OrgPermissions;
use App\Controllers\ApiController;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Calendar API Controller
 *
 */
class CalendarApiController extends ApiController
{
    use AttachmentUploads;

    private static $url_segment = 'api/v1/calendar';

    private static $allowed_actions = [
        'index',
        'participation',
        'participationTime',
        'participationFood',
        'participationNotes',
        'participationRide',
        'absence',
        'absences',
        'appointment',
        'appointmentTypes',
        'appointmentHistory',
        'orgEvents',
        'orgEvent',
        'orgEventOptions',
        'orgEventImage',
        'orgEventGallery',
        'orgEventInterest',
        'myOrgEvents',
        'meal',
        'agendaPoint',
        'members',
    ];

    /**
     * Get calendar events for a specific month
     * GET /api/v1/calendar?month=YYYY-MM
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        // Get month parameter (default to current month)
        $monthParam = $request->getVar('month');
        if ($monthParam) {
            $date = $monthParam . '-01';
        } else {
            $date = date('Y-m-01');
        }

        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));

        // Get user's organization IDs
        $organizationIDs = $member->getOrganizationIDs();

        if (empty($organizationIDs)) {
            return $this->jsonResponse([
                'year' => (int)$year,
                'month' => (int)$month,
                'events' => []
            ]);
        }

        // Get all appointments for this month in user's organizations
        $startDate = date('Y-m-01', strtotime($date));
        $endDate = date('Y-m-t', strtotime($date));

        // Mehrtägige Termine, die vor dem Monat beginnen, aber in ihn hineinreichen, gehören dazu.
        // Termine ohne Enddatum gelten als eintägig.
        $appointments = Appointment::get()
            ->filter([
                'Organisations.ID' => $organizationIDs,
                'DateStart:LessThanOrEqual' => $endDate
            ])
            ->filterAny([
                'DateStart:GreaterThanOrEqual' => $startDate,
                'DateEnd:GreaterThanOrEqual' => $startDate,
            ])
            ->distinct(true)
            ->sort('DateStart', 'ASC');

        $events = [];
        foreach ($appointments as $appointment) {
            $participation = $appointment->Participations()->filter(['MemberID' => $member->ID])->first();

            // Get meals
            // Gerichte bearbeiten (FoodFormModal per Rechtsklick): Essensplaner oder Mahlzeit-Verwalter
            $appointmentOrgIDs = $appointment->Organisations()->column('ID');
            $canEditFoods = $this->hasPermissionInAnyOrg($member, $appointmentOrgIDs, OrgPermissions::FOOD_MANAGE_MEALS)
                || $this->hasPermissionInAnyOrg($member, $appointmentOrgIDs, OrgPermissions::FOOD_APPROVE_SUGGESTIONS);
            $meals = [];
            foreach ($appointment->Meals() as $meal) {
                $mealEater = $meal->Eaters()->filter(['MemberID' => $member->ID])->first();
                $products = [];
                foreach ($meal->Foods()->filter('IsOrderable', true)->sort('ID ASC') as $food) {
                    $userOrder = MealProductOrder::get()->filter([
                        'FoodID'   => $food->ID,
                        'MealID'   => $meal->ID,
                        'MemberID' => $member->ID,
                    ])->first();
                    $productSupplier = $food->Supplier();
                    $products[] = [
                        'ID'           => $food->ID,
                        'Title'        => $food->Title,
                        'Preference'   => $food->FoodPreference ?: 'None',
                        'Supplier'     => ($productSupplier && $productSupplier->exists())
                            ? trim($productSupplier->FirstName . ' ' . $productSupplier->Surname)
                            : null,
                        'SupplierID'   => ($productSupplier && $productSupplier->exists()) ? $productSupplier->ID : null,
                        'OrganizationID' => (int) $food->ParentID,
                        'MaxQuantity'  => (int) $food->MaxQuantity,
                        'UserQuantity' => $userOrder ? (int) $userOrder->Quantity : 0,
                    ];
                }
                // Feste, bereits bestätigte Gerichte (mitgebrachte Vorschläge oder direkt fest angelegt)
                $foods = [];
                foreach ($meal->Foods()->filter(['IsOrderable' => false, 'Status' => 'Accepted'])->sort('ID ASC') as $food) {
                    $supplier = $food->Supplier();
                    $foods[] = [
                        'ID'         => $food->ID,
                        'Title'      => $food->Title,
                        'Preference' => $food->FoodPreference ?: 'None',
                        'Supplier'   => ($supplier && $supplier->exists())
                            ? trim($supplier->FirstName . ' ' . $supplier->Surname)
                            : null,
                        'SupplierID' => ($supplier && $supplier->exists()) ? $supplier->ID : null,
                        'OrganizationID' => (int) $food->ParentID,
                    ];
                }
                $meals[] = [
                    'ID'                   => $meal->ID,
                    'Title'                => $meal->Title,
                    'Time'                 => $meal->Time,
                    'RenderTime'           => $meal->RenderTime(),
                    'Description'          => $meal->Description ?: '',
                    'UserResponse'         => $mealEater ? $mealEater->Type : null,
                    'AcceptsContributions' => (bool) $meal->AcceptsContributions,
                    'Products'             => $products,
                    'Foods'                => $foods,
                    'CanEditFoods'         => $canEditFoods,
                ];
            }

            // Get agenda points
            $agendaPoints = [];
            foreach ($appointment->AgendaPoints()->sort('StartTime', 'ASC') as $point) {
                $agendaPoints[] = [
                    'ID'          => $point->ID,
                    'Title'       => $point->Title,
                    'StartTime'   => $point->StartTime,
                    'EndTime'     => $point->EndTime,
                    'Description' => $point->Description,
                    'RenderTime'  => $point->RenderTime(),
                ];
            }

            // Get all participations for this appointment
            $participations = [];
            foreach ($appointment->Participations() as $p) {
                $pMember = $p->Member();
                $participations[] = [
                    'ID'              => $p->ID,
                    'MemberID'        => $p->MemberID,
                    'MemberName'      => $pMember ? $pMember->getDisplayName() : 'Unknown',
                'Username'        => $pMember && $pMember->exists() ? ($pMember->Username ?: null) : null,
                    'Username'        => $pMember && $pMember->exists() ? ($pMember->Username ?: null) : null,
                    'ProfileImageURL' => $pMember && $pMember->hasMethod('RenderProfileImage')
                        ? $pMember->RenderProfileImage()
                        : null,
                    'Type'            => $p->Type,
                    'TimeStart'       => $p->TimeStart,
                    'TimeEnd'         => $p->TimeEnd,
                    'CustomTimeframe' => (bool) $p->CustomTimeframe,
                    'IsCurrentUser'   => $p->MemberID == $member->ID,
                    'Notes'           => $p->Notes ?: null,
                    'RideType'        => $p->RideType ?: 'None',
                    'RideSeats'       => (int) $p->RideSeats,
                ];
            }

            // Invited members / who hasn't responded yet
            $invitedMemberIDs = array_map('intval', $appointment->InvitedMembers()->column('ID'));
            $membersWithoutResponse = [];
            foreach ($appointment->getMembersWithoutResponse() as $pendingMember) {
                $membersWithoutResponse[] = [
                    'ID'              => $pendingMember->ID,
                    'MemberName'      => $pendingMember->getDisplayName(),
                    'Username'        => $pendingMember->Username ?: null,
                    'ProfileImageURL' => $pendingMember->hasMethod('RenderProfileImage')
                        ? $pendingMember->RenderProfileImage()
                        : null,
                ];
            }

            $canRecordRsvp = $this->hasPermissionInAnyOrg(
                $member,
                $appointment->Organisations()->column('ID'),
                OrgPermissions::CALENDAR_RECORD_RSVP
            );

            // Organisation logos (all organisations)
            $orgLogos = [];
            foreach ($appointment->Organisations() as $orgItem) {
                // Orgs without a logo are included too, so the frontend can show a placeholder
                $orgLogos[] = [
                    'ID'      => $orgItem->ID,
                    'Title'   => $orgItem->Title,
                    'LogoURL' => $orgItem->RenderLogo(40),
                    'Color'   => $orgItem->getLogoColor(),
                ];
            }
            $orgLogoURL = array_values(array_filter(array_column($orgLogos, 'LogoURL')))[0] ?? null;

            $events[] = [
                'ID' => $appointment->ID,
                'Title' => $appointment->Title,
                'DateStart' => $appointment->DateStart,
                'DateEnd' => $appointment->DateEnd ?: $appointment->DateStart,
                'TimeStart' => $appointment->AllDay ? null : $appointment->TimeStart,
                'TimeEnd' => $appointment->AllDay ? null : $appointment->TimeEnd,
                'AllDay' => (bool)$appointment->AllDay,
                'Location' => $appointment->Location,
                'Description' => $appointment->Description,
                'Status' => $appointment->Status,
                'EventType' => $appointment->Type()->exists() ? $appointment->Type()->Title : null,
                'TypeID' => $appointment->TypeID ?: null,
                'EventID' => $appointment->EventID ?: null,
                'EventTitle' => $appointment->EventID && $appointment->Event()->exists() ? $appointment->Event()->Title : null,
                'OrganizationIDs' => array_map('intval', $appointment->Organisations()->column('ID')),
                'ImageURL' => $appointment->Image()->exists() ? $appointment->Image()->getURL() : null,
                'OrganizationLogoURL' => $orgLogoURL,
                'OrganizationLogos' => $orgLogos,
                'UserParticipation' => $participation ? [
                    'ID'              => $participation->ID,
                    'Type'            => $participation->Type,
                    'TimeStart'       => $participation->TimeStart,
                    'TimeEnd'         => $participation->TimeEnd,
                    'CustomTimeframe' => (bool) $participation->CustomTimeframe,
                    'Notes'           => $participation->Notes ?: null,
                    'RideType'        => $participation->RideType ?: 'None',
                    'RideSeats'       => (int) $participation->RideSeats,
                ] : null,
                'EnableMeals'   => (bool)$appointment->EnableMeals,
                'EnableAgenda'  => (bool)$appointment->EnableAgenda,
                'EnableRoleCasting' => (bool)$appointment->EnableRoleCasting,
                'EventURLSegment'   => $appointment->EventID && $appointment->Event()->exists() ? $appointment->Event()->URLSegment : null,
                'RoleAssignments'   => $this->roleAssignmentsForAppointment($appointment),
                'Meals'         => $meals,
                'AgendaPoints'  => $agendaPoints,
                'Participations' => $participations,
                'InvitedMemberIDs' => $invitedMemberIDs,
                'IsInvited' => in_array($member->ID, $invitedMemberIDs, true),
                'MembersWithoutResponse' => $membersWithoutResponse,
                'CanRecordRsvp' => $canRecordRsvp,
            ];
        }

        // Get open scheduling-poll options for this month in user's organizations
        $pollOptions = SchedulingPollOption::get()
            ->filter([
                'Parent.Organisations.ID' => $organizationIDs,
                'DateStart:LessThanOrEqual' => $endDate,
            ])
            ->filterAny([
                'DateStart:GreaterThanOrEqual' => $startDate,
                'DateEnd:GreaterThanOrEqual' => $startDate,
            ])
            ->distinct(true)
            ->sort('DateStart', 'ASC');

        foreach ($pollOptions as $option) {
            $poll = $option->Parent();
            if (!$poll || !$poll->exists() || $poll->Status !== 'Open') {
                continue;
            }

            $userVote = $option->OptionParticipations()->filter(['MemberID' => $member->ID])->first();
            $optionParticipations = $this->formatPollOptionParticipations($option, $member);

            $pollInvitedMemberIDs = array_map('intval', $poll->InvitedMembers()->column('ID'));
            $respondedIDs = $option->OptionParticipations()->column('MemberID');
            $missingIDs = array_diff($pollInvitedMemberIDs, $respondedIDs);
            $membersWithoutResponse = [];
            if (!empty($missingIDs)) {
                foreach (Member::get()->filter('ID', $missingIDs) as $pendingMember) {
                    $membersWithoutResponse[] = [
                        'ID'              => $pendingMember->ID,
                        'MemberName'      => $pendingMember->getDisplayName(),
                        'Username'        => $pendingMember->Username ?: null,
                    'Username'        => $pendingMember->Username ?: null,
                        'ProfileImageURL' => $pendingMember->hasMethod('RenderProfileImage')
                            ? $pendingMember->RenderProfileImage()
                            : null,
                    ];
                }
            }

            // Geschwister-Optionen derselben Terminfindung, für die Vergleichsansicht im Dialog
            $siblingOptions = [];
            foreach ($poll->Options() as $sibling) {
                $siblingUserVote = $sibling->OptionParticipations()->filter(['MemberID' => $member->ID])->first();
                $siblingOptions[] = [
                    'OptionID'   => $sibling->ID,
                    'DateStart'  => $sibling->DateStart,
                    'DateEnd'    => $sibling->DateEnd,
                    'TimeStart'  => $sibling->AllDay ? null : $sibling->TimeStart,
                    'TimeEnd'    => $sibling->AllDay ? null : $sibling->TimeEnd,
                    'AllDay'     => (bool) $sibling->AllDay,
                    'RenderDate' => $sibling->RenderDate(),
                    'RenderTime' => $sibling->RenderTime(),
                    'VotedYes'   => $sibling->getVotedYes(),
                    'VotedMaybe' => $sibling->getVotedMaybe(),
                    'VotedNo'    => $sibling->getVotedNo(),
                    'UserVote'   => $siblingUserVote ? $siblingUserVote->Type : null,
                    'Participations' => $this->formatPollOptionParticipations($sibling, $member),
                ];
            }

            $pollOrgLogos = [];
            foreach ($poll->Organisations() as $orgItem) {
                // Orgs without a logo are included too, so the frontend can show a placeholder
                $pollOrgLogos[] = [
                    'ID'      => $orgItem->ID,
                    'Title'   => $orgItem->Title,
                    'LogoURL' => $orgItem->RenderLogo(40),
                    'Color'   => $orgItem->getLogoColor(),
                ];
            }
            $pollOrgLogoURL = array_values(array_filter(array_column($pollOrgLogos, 'LogoURL')))[0] ?? null;

            $events[] = [
                // Negative ID, damit Terminfindungs-Pseudo-Events nie mit echten Appointment-IDs kollidieren.
                'ID' => -$option->ID,
                'Title' => $poll->Title,
                'DateStart' => $option->DateStart,
                'DateEnd' => $option->DateEnd ?: $option->DateStart,
                'TimeStart' => $option->AllDay ? null : $option->TimeStart,
                'TimeEnd' => $option->AllDay ? null : $option->TimeEnd,
                'AllDay' => (bool) $option->AllDay,
                'Location' => $poll->Location,
                'Description' => $poll->Description,
                'Status' => 'Scheduled',
                'EventType' => null,
                'TypeID' => null,
                'OrganizationIDs' => array_map('intval', $poll->Organisations()->column('ID')),
                'ImageURL' => null,
                'OrganizationLogoURL' => $pollOrgLogoURL,
                'OrganizationLogos' => $pollOrgLogos,
                'UserParticipation' => $userVote ? [
                    'ID'   => $userVote->ID,
                    'Type' => $userVote->Type,
                ] : null,
                'EnableMeals'   => false,
                'EnableAgenda'  => false,
                'EnableRoleCasting' => false,
                'RoleAssignments'   => [],
                'Meals'         => [],
                'AgendaPoints'  => [],
                'Participations' => $optionParticipations,
                'InvitedMemberIDs' => $pollInvitedMemberIDs,
                'IsInvited' => in_array($member->ID, $pollInvitedMemberIDs, true),
                'MembersWithoutResponse' => $membersWithoutResponse,
                'IsPoll' => true,
                'PollID' => $poll->ID,
                'OptionID' => $option->ID,
                'PollOptions' => $siblingOptions,
            ];
        }

        return $this->jsonResponse([
            'year' => (int)$year,
            'month' => (int)$month,
            'events' => $events
        ]);
    }

    /**
     * Change participation
     * POST /api/v1/calendar/participation/:id
     * Body: { response: "Accept|Maybe|Decline", targetMemberId?: int }
     */
    public function participation(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $appointmentID = $request->param('ID');
        $appointment = Appointment::get()->byID($appointmentID);

        if (!$appointment) {
            return $this->errorResponse('Event not found', 404);
        }

        // Security check: Verify user has access via organisations
        $organizationIDs = $member->getOrganizationIDs();
        $sharedOrgs = $appointment->Organisations()->filter('ID', $organizationIDs);
        if (!$sharedOrgs->exists()) {
            return $this->errorResponse('Access denied', 403);
        }

        // Get response type from request
        $body = json_decode($request->getBody(), true);
        $type = $body['response'] ?? null;

        $targetMember = $this->resolveRsvpTargetMember(
            $body['targetMemberId'] ?? null,
            $member,
            $appointment->Organisations()->column('ID'),
            OrgPermissions::CALENDAR_RECORD_RSVP
        );
        if ($targetMember instanceof HTTPResponse) {
            return $targetMember;
        }

        $participation = $appointment->Participations()->filter(['MemberID' => $targetMember->ID])->first();

        if (!$type) {
            // Zurück auf "Ohne Antwort": Teilnahme entfernen, Notiz/Zeitraum/Mitfahrt
            // aber für die nächste Antwort aufheben (siehe AppointmentParticipationStash)
            if ($participation) {
                AppointmentParticipationStash::stash($participation);
                $participation->delete();
            }
            return $this->successResponse([
                'ID'              => null,
                'MemberID'        => $targetMember->ID,
                'Type'            => null,
                'TimeStart'       => null,
                'TimeEnd'         => null,
                'CustomTimeframe' => false,
                'Notes'           => null,
                'RideType'        => 'None',
                'RideSeats'       => 0,
            ], 'Participation removed');
        }

        if (!in_array($type, ['Accept', 'Maybe', 'Decline'])) {
            return $this->errorResponse('Invalid participation type', 400);
        }

        // Find or create participation
        if (!$participation) {
            $participation = AppointmentParticipation::create();
            $participation->ParentID        = $appointment->ID;
            $participation->MemberID        = $targetMember->ID;
            $participation->CustomTimeframe = false;
            AppointmentParticipationStash::restoreInto($participation);
        }

        $participation->Type = $type;
        $participation->write();

        return $this->successResponse([
            'ID'              => $participation->ID,
            'MemberID'        => $targetMember->ID,
            'Type'            => $participation->Type,
            'TimeStart'       => $participation->TimeStart,
            'TimeEnd'         => $participation->TimeEnd,
            'CustomTimeframe' => (bool) $participation->CustomTimeframe,
            'Notes'           => $participation->Notes ?: null,
            'RideType'        => $participation->RideType ?: 'None',
            'RideSeats'       => (int) $participation->RideSeats,
        ], 'Participation updated');
    }

    /**
     * Change participation time
     * POST /api/v1/calendar/participationTime/:id
     * Body: { timestart: "HH:mm:ss", timeend: "HH:mm:ss" }
     */
    public function participationTime(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $appointmentID = $request->param('ID');
        $appointment = Appointment::get()->byID($appointmentID);

        if (!$appointment) {
            return $this->errorResponse('Event not found', 404);
        }

        // Security check
        $organizationIDs = $member->getOrganizationIDs();
        $sharedOrgs = $appointment->Organisations()->filter('ID', $organizationIDs);
        if (!$sharedOrgs->exists()) {
            return $this->errorResponse('Access denied', 403);
        }

        $body = json_decode($request->getBody(), true);
        $timestart = $body['timestart'] ?? null;
        $timeend   = $body['timeend'] ?? null;

        $participation = $appointment->Participations()->filter(['MemberID' => $member->ID])->first();

        if (!$participation) {
            return $this->errorResponse('No participation found', 404);
        }

        if (!$timestart && !$timeend) {
            // Clear custom time
            $participation->TimeStart       = null;
            $participation->TimeEnd         = null;
            $participation->CustomTimeframe = false;
        } else {
            if (!$timestart || !$timeend) {
                return $this->errorResponse('Both time fields are required', 400);
            }
            $participation->TimeStart       = $timestart;
            $participation->TimeEnd         = $timeend;
            $participation->CustomTimeframe = true;
        }

        $participation->write();

        return $this->successResponse([
            'TimeStart'       => $participation->TimeStart,
            'TimeEnd'         => $participation->TimeEnd,
            'CustomTimeframe' => (bool) $participation->CustomTimeframe,
        ], 'Time updated');
    }

    /**
     * Change food participation
     * POST /api/v1/calendar/participationFood/:mealId
     * Body: { response: "Accept|Decline", targetMemberId?: int }
     */
    public function participationFood(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $mealID = $request->param('ID');
        $meal = Meal::get()->byID($mealID);

        if (!$meal) {
            return $this->errorResponse('Meal not found', 404);
        }

        // Security check
        $appointment = $meal->Parent();
        if (!$appointment || !$appointment->exists()) {
            return $this->errorResponse('Meal has no parent appointment', 500);
        }

        $organizationIDs = $member->getOrganizationIDs();
        $sharedOrgs = $appointment->Organisations()->filter('ID', $organizationIDs);
        if (!$sharedOrgs->exists()) {
            return $this->errorResponse('Access denied', 403);
        }

        $body = json_decode($request->getBody(), true);
        $type = $body['response'] ?? null;

        $targetMember = $this->resolveRsvpTargetMember(
            $body['targetMemberId'] ?? null,
            $member,
            $appointment->Organisations()->column('ID'),
            OrgPermissions::FOOD_RECORD_RSVP
        );
        if ($targetMember instanceof HTTPResponse) {
            return $targetMember;
        }

        $mealEater = $meal->Eaters()->filter(['MemberID' => $targetMember->ID])->first();

        if (!$type) {
            // Zurück auf "Ohne Antwort": komplette Teilnahme entfernen
            if ($mealEater) {
                $mealEater->delete();
            }
            return $this->successResponse([
                'ID'       => null,
                'MemberID' => $targetMember->ID,
                'Type'     => null,
            ], 'Food participation removed');
        }

        if (!in_array($type, ['Accept', 'Decline'])) {
            return $this->errorResponse('Invalid food response type', 400);
        }

        if (!$mealEater) {
            $mealEater = MealEater::create();
            $mealEater->ParentID = $meal->ID;
            $mealEater->MemberID = $targetMember->ID;
        }

        $mealEater->Type = $type;
        $mealEater->write();

        return $this->successResponse([
            'ID'       => $mealEater->ID,
            'MemberID' => $targetMember->ID,
            'Type'     => $mealEater->Type
        ], 'Food participation updated');
    }

    /**
     * Resolves the member a Zu-/Absage should be recorded for: the authenticated
     * member itself, or — when a targetMemberId is given and the authenticated
     * member holds the given permission in at least one of the shared orgs — an
     * arbitrary other member of one of those orgs (to record verbal RSVPs on
     * their behalf). Returns an HTTPResponse (error) on failure.
     */
    private function resolveRsvpTargetMember(
        $targetMemberId,
        Member $member,
        array $orgIDs,
        string $permissionCode
    ) {
        if (!$targetMemberId || (int) $targetMemberId === (int) $member->ID) {
            return $member;
        }

        if (!$this->hasPermissionInAnyOrg($member, $orgIDs, $permissionCode)) {
            return $this->errorResponse('Access denied', 403);
        }

        $targetMember = Member::get()->byID($targetMemberId);
        if (!$targetMember) {
            return $this->errorResponse('Member not found', 404);
        }

        $memberOrgIDs = OrganizationMembership::get()
            ->filter(['OrganizationID' => $orgIDs, 'MemberID' => $targetMember->ID, 'Role' => 'member'])
            ->column('OrganizationID');
        if (empty($memberOrgIDs)) {
            return $this->errorResponse('Target member is not part of a shared organisation', 403);
        }

        return $targetMember;
    }

    /**
     * Change participation notes
     * POST /api/v1/calendar/participationNotes/:id
     * Body: { notes: "..." }
     */
    public function participationNotes(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $appointmentID = $request->param('ID');
        $appointment = Appointment::get()->byID($appointmentID);

        if (!$appointment) {
            return $this->errorResponse('Event not found', 404);
        }

        // Security check
        $organizationIDs = $member->getOrganizationIDs();
        $sharedOrgs = $appointment->Organisations()->filter('ID', $organizationIDs);
        if (!$sharedOrgs->exists()) {
            return $this->errorResponse('Access denied', 403);
        }

        $participation = $appointment->Participations()->filter(['MemberID' => $member->ID])->first();

        if (!$participation) {
            return $this->errorResponse('No participation found', 404);
        }

        $body = json_decode($request->getBody(), true);
        $notes = isset($body['notes']) ? trim((string) $body['notes']) : null;

        $participation->Notes = $notes ?: null;
        $participation->write();

        return $this->successResponse([
            'Notes' => $participation->Notes ?: null,
        ], 'Notes updated');
    }

    /**
     * Change participation ride-sharing (Anfahrt)
     * POST /api/v1/calendar/participationRide/:id
     * Body: { rideType: "Need"|"Offer"|null, seats: number }
     */
    public function participationRide(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $appointmentID = $request->param('ID');
        $appointment = Appointment::get()->byID($appointmentID);

        if (!$appointment) {
            return $this->errorResponse('Event not found', 404);
        }

        // Security check
        $organizationIDs = $member->getOrganizationIDs();
        $sharedOrgs = $appointment->Organisations()->filter('ID', $organizationIDs);
        if (!$sharedOrgs->exists()) {
            return $this->errorResponse('Access denied', 403);
        }

        $participation = $appointment->Participations()->filter(['MemberID' => $member->ID])->first();

        if (!$participation) {
            return $this->errorResponse('No participation found', 404);
        }

        $body = json_decode($request->getBody(), true);
        $rideType = $body['rideType'] ?? null;

        if (!$rideType || !in_array($rideType, ['Need', 'Offer'])) {
            $participation->RideType  = 'None';
            $participation->RideSeats = 0;
        } else {
            $seats = (int) ($body['seats'] ?? 1);
            $participation->RideType  = $rideType;
            $participation->RideSeats = $rideType === 'Offer' ? max(0, min(8, $seats)) : 0;
        }

        $participation->write();

        return $this->successResponse([
            'RideType'  => $participation->RideType,
            'RideSeats' => (int) $participation->RideSeats,
        ], 'Ride updated');
    }

    /**
     * Create an absence for the current user.
     * POST /api/v1/calendar/absence
     * Body: { dateStart, dateEnd, recurrence, organizationIds[] }
     */
    public function absence(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $body = json_decode($request->getBody(), true) ?? [];

        // DELETE
        if ($request->httpMethod() === 'DELETE') {
            $id = (int) ($request->getVar('id') ?? 0);
            $absence = Absence::get()->byID($id);
            if (!$absence || $absence->MemberID !== $member->ID) {
                return $this->errorResponse('Nicht gefunden oder keine Berechtigung', 404);
            }
            $absence->delete();
            return $this->successResponse([], 'Abwesenheit gelöscht');
        }

        // PUT (update)
        if ($request->httpMethod() === 'PUT') {
            $id = (int) ($body['id'] ?? 0);
            $absence = Absence::get()->byID($id);
            if (!$absence || $absence->MemberID !== $member->ID) {
                return $this->errorResponse('Nicht gefunden oder keine Berechtigung', 404);
            }
            $absence->DateStart  = $body['dateStart'] ?? $absence->DateStart;
            $absence->DateEnd    = $body['dateEnd'] ?? $absence->DateStart;
            $absence->Recurrence = in_array($body['recurrence'] ?? '', ['Never', 'Daily', 'Weekly', 'Monthly', 'Yearly'])
                ? $body['recurrence'] : $absence->Recurrence;
            $absence->Note = $body['note'] ?? null;
            $absence->write();

            return $this->successResponse(['ID' => $absence->ID], 'Abwesenheit aktualisiert');
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $dateStart = $body['dateStart'] ?? null;
        $dateEnd   = $body['dateEnd'] ?? $dateStart;

        if (!$dateStart) {
            return $this->errorResponse('dateStart is required', 400);
        }

        $recurrence = $body['recurrence'] ?? 'Never';
        if (!in_array($recurrence, ['Never', 'Daily', 'Weekly', 'Monthly', 'Yearly'])) {
            $recurrence = 'Never';
        }

        $absence = Absence::create();
        $absence->DateStart  = $dateStart;
        $absence->DateEnd    = $dateEnd;
        $absence->Recurrence = $recurrence;
        $absence->Note       = $body['note'] ?? null;
        $absence->MemberID   = $member->ID;
        $absence->write();

        return $this->successResponse(['ID' => $absence->ID], 'Abwesenheit eingetragen');
    }

    /**
     * Return absent members for a date, or per-day counts for a month.
     * GET /api/v1/calendar/absences?date=YYYY-MM-DD
     * GET /api/v1/calendar/absences?month=YYYY-MM  → { absenceCounts: { "YYYY-MM-DD": n } }
     */
    public function absences(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $organizationIDs = $member->getOrganizationIDs();

        $monthParam = $request->getVar('month');
        if ($monthParam) {
            if (empty($organizationIDs)) {
                return $this->jsonResponse(['absenceCounts' => (object)[]]);
            }
            $allAbsences = $this->getRelevantAbsences($organizationIDs);
            $year  = (int) date('Y', strtotime($monthParam . '-01'));
            $month = (int) date('m', strtotime($monthParam . '-01'));
            $days  = (int) date('t', strtotime($monthParam . '-01'));
            $counts = [];
            for ($d = 1; $d <= $days; $d++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $count = 0;
                $seen  = [];
                foreach ($allAbsences as $absence) {
                    if (isset($seen[$absence->MemberID])) {
                        continue;
                    }
                    if ($absence->appliesToDate($date)) {
                        $seen[$absence->MemberID] = true;
                        $count++;
                    }
                }
                if ($count > 0) {
                    $counts[$date] = $count;
                }
            }
            return $this->jsonResponse(['absenceCounts' => $counts ?: (object)[]]);
        }

        $date = $request->getVar('date') ?: date('Y-m-d');

        if (empty($organizationIDs)) {
            return $this->jsonResponse(['absences' => []]);
        }

        $allAbsences = $this->getRelevantAbsences($organizationIDs);
        $result = [];
        $seen   = [];

        foreach ($allAbsences as $absence) {
            if (!$absence->appliesToDate($date)) {
                continue;
            }

            $absentMemberID = $absence->MemberID;
            if (isset($seen[$absentMemberID])) {
                continue;
            }
            $seen[$absentMemberID] = true;

            $absentMember = $absence->Member();
            $memberExists = $absentMember && $absentMember->exists();
            $imageURL = null;
            if ($memberExists && $absentMember->hasMethod('RenderProfileImage')) {
                $imageURL = $absentMember->RenderProfileImage();
            }

            $result[] = [
                'AbsenceID'       => $absence->ID,
                'MemberID'        => $absentMemberID,
                'MemberName'      => $memberExists ? $absentMember->getDisplayName() : 'Gelöschter Benutzer',
                'ProfileImageURL' => $imageURL,
                'Note'            => $absence->Note ?: null,
                'DateStart'       => $absence->DateStart,
                'DateEnd'         => $absence->DateEnd,
                'Recurrence'      => $absence->Recurrence,
            ];
        }

        return $this->jsonResponse(['absences' => $result]);
    }

    /**
     * Create a new appointment (requires CALENDAR_MANAGE).
     * POST /api/v1/calendar/appointment
     * Body: { title, dateStart, dateEnd, timeStart, timeEnd, allDay, location, description, status, typeId, organizationIds[] }
     */
    public function appointment(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $body  = json_decode($request->getBody(), true) ?? [];

        // DELETE
        if ($request->httpMethod() === 'DELETE') {
            $id = (int) ($request->getVar('id') ?? 0);
            $appt = Appointment::get()->byID($id);
            if (!$appt) {
                return $this->errorResponse('Nicht gefunden', 404);
            }
            $apptOrgIDs = $appt->Organisations()->column('ID');
            $hasPermission = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_DELETE);
            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }
            $appt->Organisations()->removeAll();
            $appt->InvitedMembers()->removeAll();
            $appt->Participations()->removeAll();
            $appt->delete();
            return $this->successResponse([], 'Termin gelöscht');
        }

        // PUT (update)
        if ($request->httpMethod() === 'PUT') {
            $id = (int) ($body['id'] ?? 0);
            $appt = Appointment::get()->byID($id);
            if (!$appt) {
                return $this->errorResponse('Nicht gefunden', 404);
            }
            $apptOrgIDs = $appt->Organisations()->column('ID');
            $hasPermission = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_MANAGE);
            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }
            $allDay = !empty($body['allDay']);
            $appt->Title        = trim($body['title'] ?? '') ?: $appt->Title;
            $appt->DateStart    = $body['dateStart'] ?? $appt->DateStart;
            $appt->DateEnd      = $body['dateEnd'] ?: ($body['dateStart'] ?? $appt->DateEnd);
            $appt->TimeStart    = $allDay ? null : ($body['timeStart'] ?: null);
            $appt->TimeEnd      = $allDay ? null : ($body['timeEnd'] ?: null);
            $appt->AllDay       = $allDay;
            $appt->Location     = $body['location'] ?? '';
            $appt->Description  = $body['description'] ?? '';
            $appt->Status       = in_array($body['status'] ?? '', ['Suggested', 'Scheduled', 'Cancelled'])
                ? $body['status'] : $appt->Status;
            $appt->TypeID       = !empty($body['typeId']) ? (int) $body['typeId'] : 0;
            if (array_key_exists('eventId', $body)) {
                $newOrgIDsForEvent = array_map('intval', $body['organizationIds'] ?? []) ?: $apptOrgIDs;
                $appt->EventID = $this->resolveEventID($body['eventId'], $newOrgIDsForEvent);
            }
            if (array_key_exists('enableMeals', $body)) {
                $appt->EnableMeals = (bool) $body['enableMeals'];
            }
            if (array_key_exists('enableAgenda', $body)) {
                $appt->EnableAgenda = (bool) $body['enableAgenda'];
            }
            if (array_key_exists('enableRoleCasting', $body)) {
                $appt->EnableRoleCasting = (bool) $body['enableRoleCasting'];
            }
            $appt->write();
            $newOrgIDs = array_map('intval', $body['organizationIds'] ?? []);
            if (!empty($newOrgIDs)) {
                $appt->trackHistoryRelation('Organisations', function () use ($appt, $newOrgIDs) {
                    $appt->Organisations()->setByIDList($newOrgIDs);
                });
            }

            $effectiveOrgIDs = !empty($newOrgIDs) ? $newOrgIDs : $apptOrgIDs;
            $validMemberIDs = OrganizationMembership::get()->filter([
                'OrganizationID' => $effectiveOrgIDs,
                'Role'           => 'member',
            ])->column('MemberID');
            $invitedIDs = array_values(array_intersect(
                array_map('intval', $body['invitedMemberIds'] ?? []),
                $validMemberIDs
            ));
            $appt->trackHistoryRelation('InvitedMembers', function () use ($appt, $invitedIDs) {
                $appt->InvitedMembers()->setByIDList($invitedIDs);
            });

            return $this->successResponse(['ID' => $appt->ID], 'Termin aktualisiert');
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $orgIDs = array_map('intval', $body['organizationIds'] ?? []);

        if (empty($orgIDs)) {
            return $this->errorResponse('Mindestens eine Organisation muss gewählt werden', 400);
        }

        // Verify the user has CALENDAR_MANAGE in at least one of the chosen organisations
        $hasPermission = $this->hasPermissionInAnyOrg($member, $orgIDs, OrgPermissions::CALENDAR_MANAGE);

        if (!$hasPermission) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $title = trim($body['title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $dateStart = $body['dateStart'] ?? null;
        if (!$dateStart) {
            return $this->errorResponse('Startdatum ist erforderlich', 400);
        }

        $validMemberIDs = OrganizationMembership::get()->filter([
            'OrganizationID' => $orgIDs,
            'Role'           => 'member',
        ])->column('MemberID');
        $invitedIDs = array_values(array_intersect(
            array_map('intval', $body['invitedMemberIds'] ?? []),
            $validMemberIDs
        ));

        $allDay = !empty($body['allDay']);

        $appt = Appointment::create();
        $appt->Title       = $title;
        $appt->DateStart   = $dateStart;
        $appt->DateEnd     = $body['dateEnd'] ?: $dateStart;
        $appt->TimeStart   = $allDay ? null : ($body['timeStart'] ?: null);
        $appt->TimeEnd     = $allDay ? null : ($body['timeEnd'] ?: null);
        $appt->AllDay      = $allDay;
        $appt->Location    = $body['location'] ?? '';
        $appt->Description = $body['description'] ?? '';
        $appt->Status      = in_array($body['status'] ?? '', ['Suggested', 'Scheduled', 'Cancelled'])
            ? $body['status']
            : 'Scheduled';

        if (!empty($body['typeId'])) {
            $appt->TypeID = (int) $body['typeId'];
        }
        $appt->EventID = $this->resolveEventID($body['eventId'] ?? null, $orgIDs);
        $appt->EnableMeals = !array_key_exists('enableMeals', $body) || !empty($body['enableMeals']);
        $appt->EnableAgenda = !array_key_exists('enableAgenda', $body) || !empty($body['enableAgenda']);
        $appt->EnableRoleCasting = !empty($body['enableRoleCasting']);

        $appt->write();
        $appt->Organisations()->addMany($orgIDs);
        $appt->InvitedMembers()->addMany($invitedIDs);

        // Auto-decline members who are absent on the appointment's start date
        $absentEntries = $this->getRelevantAbsences($orgIDs);
        foreach ($absentEntries as $absence) {
            if (!$absence->appliesToDate($appt->DateStart)) {
                continue;
            }
            $alreadyExists = $appt->Participations()
                ->filter(['MemberID' => $absence->MemberID])
                ->exists();
            if (!$alreadyExists) {
                $p = AppointmentParticipation::create();
                $p->ParentID = $appt->ID;
                $p->MemberID = $absence->MemberID;
                $p->Type     = 'Decline';
                $p->write();
            }
        }

        return $this->successResponse(['ID' => $appt->ID], 'Termin erstellt');
    }

    /**
     * Änderungsverlauf eines Termins inkl. Zu-/Absagen.
     * GET /api/v1/calendar/appointmentHistory/:id?before=<EntryID>&limit=20
     */
    public function appointmentHistory(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $appointment = Appointment::get()->byID((int) $request->param('ID'));
        if (!$appointment) {
            return $this->errorResponse('Termin nicht gefunden', 404);
        }

        $sharedOrgs = $appointment->Organisations()->filter('ID', $member->getOrganizationIDs() ?: [0]);
        if (!$sharedOrgs->exists()) {
            return $this->errorResponse('Access denied', 403);
        }

        return $this->historyResponse($appointment, $request);
    }

    /**
     * Create/update/delete a meal of an appointment.
     * POST   /api/v1/calendar/meal/:appointmentId — create (requires CALENDAR_MANAGE)
     * PUT    /api/v1/calendar/meal/:mealId        — update (CALENDAR_MANAGE or FOOD_MANAGE_MEALS)
     * DELETE /api/v1/calendar/meal/:mealId        — delete (requires CALENDAR_MANAGE)
     * Body: { title, time (HH:mm), description?, acceptsContributions? }
     */
    public function meal(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $method = $request->httpMethod();
        $id     = $request->param('ID');

        // PUT /api/v1/calendar/meal/:mealId — update existing meal
        if ($method === 'PUT') {
            $meal = Meal::get()->byID($id);
            if (!$meal || !$meal->exists()) {
                return $this->errorResponse('Mahlzeit nicht gefunden', 404);
            }

            // Bearbeitbar aus dem Termin (Termine verwalten) und von der Mahlzeit-Seite (Mahlzeiten verwalten)
            $apptOrgIDs = $meal->Parent()->Organisations()->column('ID');
            $hasPermission = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_MANAGE)
                || $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::FOOD_MANAGE_MEALS);

            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $body  = $this->getJsonBody();
            $title = trim($body['title'] ?? '');
            $time  = trim($body['time'] ?? '');

            if (!$title) {
                return $this->errorResponse('Titel ist erforderlich', 400);
            }
            if (mb_strlen($title) > Meal::TITLE_MAX_LENGTH) {
                return $this->errorResponse('Der Titel darf höchstens ' . Meal::TITLE_MAX_LENGTH . ' Zeichen lang sein', 400);
            }
            if (!$time) {
                return $this->errorResponse('Uhrzeit ist erforderlich', 400);
            }
            if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                $time .= ':00';
            }

            $meal->Title = $title;
            $meal->Time  = $time;
            $meal->AcceptsContributions = (bool) ($body['acceptsContributions'] ?? false);
            if (array_key_exists('description', $body)) {
                $meal->Description = trim((string) $body['description']);
            }
            $meal->write();

            return $this->successResponse([
                'ID'                   => $meal->ID,
                'Title'                => $meal->Title,
                'Time'                 => $meal->Time,
                'RenderTime'           => $meal->RenderTime(),
                'Description'          => $meal->Description ?: '',
                'AcceptsContributions' => (bool) $meal->AcceptsContributions,
            ], 'Mahlzeit aktualisiert');
        }

        // DELETE /api/v1/calendar/meal/:mealId — delete meal
        if ($method === 'DELETE') {
            $meal = Meal::get()->byID($id);
            if (!$meal || !$meal->exists()) {
                return $this->errorResponse('Mahlzeit nicht gefunden', 404);
            }

            $apptOrgIDs = $meal->Parent()->Organisations()->column('ID');
            $hasPermission = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_MANAGE);

            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $meal->Eaters()->removeAll();
            $meal->delete();

            return $this->successResponse([], 'Mahlzeit gelöscht');
        }

        // POST /api/v1/calendar/meal/:appointmentId — create meal
        if ($method !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $appointment = Appointment::get()->byID($id);
        if (!$appointment) {
            return $this->errorResponse('Termin nicht gefunden', 404);
        }

        $apptOrgIDs = $appointment->Organisations()->column('ID');
        $hasPermission = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_MANAGE);

        if (!$hasPermission) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();

        $title = trim($body['title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }
        if (mb_strlen($title) > Meal::TITLE_MAX_LENGTH) {
            return $this->errorResponse('Der Titel darf höchstens ' . Meal::TITLE_MAX_LENGTH . ' Zeichen lang sein', 400);
        }

        $time = trim($body['time'] ?? '');
        if (!$time) {
            return $this->errorResponse('Uhrzeit ist erforderlich', 400);
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        $meal = Meal::create();
        $meal->Title    = $title;
        $meal->Time     = $time;
        $meal->ParentID = $appointment->ID;
        $meal->AcceptsContributions = (bool) ($body['acceptsContributions'] ?? false);
        $meal->Description = trim((string) ($body['description'] ?? ''));
        $meal->write();

        return $this->successResponse([
            'ID'                   => $meal->ID,
            'Title'                => $meal->Title,
            'Time'                 => $meal->Time,
            'RenderTime'           => $meal->RenderTime(),
            'Description'          => $meal->Description ?: '',
            'AcceptsContributions' => (bool) $meal->AcceptsContributions,
            'UserResponse'         => null,
            'Products'             => [],
            'Foods'                => [],
        ], 'Mahlzeit hinzugefügt');
    }

    /**
     * CRUD for appointment agenda points (requires CALENDAR_MANAGE).
     * POST   /api/v1/calendar/agendaPoint/:appointmentId  — create
     * PUT    /api/v1/calendar/agendaPoint/:agendaPointId  — update
     * DELETE /api/v1/calendar/agendaPoint/:agendaPointId  — delete
     */
    public function agendaPoint(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $method = $request->httpMethod();
        $id     = (int) $request->param('ID');

        if ($method === 'PUT') {
            $point = AppointmentAgendaPoint::get()->byID($id);
            if (!$point || !$point->exists()) {
                return $this->errorResponse('Tagesordnungspunkt nicht gefunden', 404);
            }
            $apptOrgIDs = $point->Parent()->Organisations()->column('ID');
            $hasPermission = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_MANAGE);
            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $body = $this->getJsonBody();
            $title = trim($body['title'] ?? '');
            if (!$title) {
                return $this->errorResponse('Titel ist erforderlich', 400);
            }
            $startTime = $body['startTime'] ?? null;
            $endTime   = $body['endTime'] ?? null;
            if ($startTime && preg_match('/^\d{2}:\d{2}$/', $startTime)) {
                $startTime .= ':00';
            }
            if ($endTime && preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                $endTime .= ':00';
            }

            $point->Title       = $title;
            $point->StartTime   = $startTime ?: null;
            $point->EndTime     = $endTime ?: null;
            $point->Description = $body['description'] ?? '';
            $point->write();

            return $this->successResponse([
                'ID'          => $point->ID,
                'Title'       => $point->Title,
                'StartTime'   => $point->StartTime,
                'EndTime'     => $point->EndTime,
                'Description' => $point->Description,
                'RenderTime'  => $point->RenderTime(),
            ], 'Tagesordnungspunkt aktualisiert');
        }

        if ($method === 'DELETE') {
            $point = AppointmentAgendaPoint::get()->byID($id);
            if (!$point || !$point->exists()) {
                return $this->errorResponse('Tagesordnungspunkt nicht gefunden', 404);
            }
            $apptOrgIDs = $point->Parent()->Organisations()->column('ID');
            $hasPermission = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_MANAGE);
            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $point->delete();
            return $this->successResponse([], 'Tagesordnungspunkt gelöscht');
        }

        if ($method !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $appointment = Appointment::get()->byID($id);
        if (!$appointment) {
            return $this->errorResponse('Termin nicht gefunden', 404);
        }
        $apptOrgIDs = $appointment->Organisations()->column('ID');
        $hasPermission = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_MANAGE);
        if (!$hasPermission) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $title = trim($body['title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }
        $startTime = $body['startTime'] ?? null;
        $endTime   = $body['endTime'] ?? null;
        if ($startTime && preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            $startTime .= ':00';
        }
        if ($endTime && preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            $endTime .= ':00';
        }

        $point = AppointmentAgendaPoint::create();
        $point->Title       = $title;
        $point->StartTime   = $startTime ?: null;
        $point->EndTime     = $endTime ?: null;
        $point->Description = $body['description'] ?? '';
        $point->ParentID    = $appointment->ID;
        $point->write();

        return $this->successResponse([
            'ID'          => $point->ID,
            'Title'       => $point->Title,
            'StartTime'   => $point->StartTime,
            'EndTime'     => $point->EndTime,
            'Description' => $point->Description,
            'RenderTime'  => $point->RenderTime(),
        ], 'Tagesordnungspunkt hinzugefügt');
    }

    /**
     * Formatiert die Teilnahmen einer Terminfindungs-Option fürs Frontend.
     */
    private function formatPollOptionParticipations(SchedulingPollOption $option, Member $member): array
    {
        $participations = [];
        foreach ($option->OptionParticipations() as $p) {
            $pMember = $p->Member();
            $participations[] = [
                'ID'              => $p->ID,
                'MemberID'        => $p->MemberID,
                'MemberName'      => $pMember ? $pMember->getDisplayName() : 'Unknown',
                'Username'        => $pMember && $pMember->exists() ? ($pMember->Username ?: null) : null,
                'ProfileImageURL' => $pMember && $pMember->hasMethod('RenderProfileImage')
                    ? $pMember->RenderProfileImage()
                    : null,
                'Type'            => $p->Type,
                'IsCurrentUser'   => $p->MemberID == $member->ID,
            ];
        }
        return $participations;
    }

    /**
     * Fetch all Absence records for members of the given organisations. An
     * absence always applies to every organisation/calendar its member is
     * part of, so no further per-org scoping is needed here.
     *
     * @param int[] $organizationIDs
     * @return \App\Calendar\Absence[]
     */
    private function getRelevantAbsences(array $organizationIDs)
    {
        $memberIDsInOrgs = OrganizationMembership::get()
            ->filter(['OrganizationID' => $organizationIDs, 'Role' => 'member'])
            ->column('MemberID');

        // Materialized as an array (not returned lazily) since callers iterate
        // it repeatedly, e.g. once per day of a month.
        return Absence::get()->filter(['MemberID' => $memberIDsInOrgs])->toArray();
    }

    /**
     * List members of the given organisations, for the appointment invite picker.
     * GET /api/v1/calendar/members?organizationIds=1,2,3
     */
    public function members(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDsParam = (string) ($request->getVar('organizationIds') ?? '');
        $requestedOrgIDs = array_filter(array_map('intval', explode(',', $orgIDsParam)));
        $orgIDs = array_values(array_intersect($requestedOrgIDs, $member->getOrganizationIDs()));

        if (empty($orgIDs)) {
            return $this->jsonResponse(['members' => []]);
        }

        $memberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $orgIDs,
            'Role'           => 'member',
        ]);

        $seen = [];
        $members = [];
        foreach ($memberships as $ms) {
            $m = $ms->Member();
            if (!$m || !$m->exists() || isset($seen[$m->ID])) {
                continue;
            }
            $seen[$m->ID] = true;
            $members[] = [
                'ID'     => $m->ID,
                'Name'   => $m->getDisplayName(),
                'Avatar' => $m->hasMethod('RenderProfileImage') ? $m->RenderProfileImage() : null,
            ];
        }

        return $this->jsonResponse(['members' => $members]);
    }

    /**
     * List all appointment types.
     * GET /api/v1/calendar/appointmentTypes
     */
    /**
     * Rollenplan eines Termins: die Rollenzuteilungen seines Events an den Tagen des Termins
     * (leer, wenn der Rollenplan aus ist oder der Termin zu keinem Event gehört)
     */
    private function roleAssignmentsForAppointment(Appointment $appointment): array
    {
        if (!$appointment->EnableRoleCasting || !$appointment->EventID) {
            return [];
        }
        $assignments = ScriptRoleAssignment::get()->filter([
            'EventID'          => $appointment->EventID,
            'Date:GreaterThanOrEqual' => $appointment->DateStart,
            'Date:LessThanOrEqual'    => $appointment->DateEnd ?: $appointment->DateStart,
        ]);
        $result = [];
        foreach ($assignments as $assignment) {
            $role = $assignment->Role();
            $result[] = array_merge($assignment->toApi(), [
                'RoleTitle'   => $role->Title,
                'ScriptTitle' => $role->Script()->Title,
            ]);
        }
        // Nach Rolle, dann Uhrzeit (ganztägig zuerst)
        usort($result, fn ($a, $b) => [$a['Date'], $a['RoleTitle'], $a['TimeStart'] ?? ''] <=> [$b['Date'], $b['RoleTitle'], $b['TimeStart'] ?? '']);
        return $result;
    }

    /**
     * Event-ID aus dem Request übernehmen — nur wenn das Event zu einer der
     * Organisationen des Termins gehört, sonst 0 (kein Event).
     *
     * @param int[] $orgIDs
     */
    private function resolveEventID($eventID, array $orgIDs): int
    {
        $eventID = (int) $eventID;
        if (!$eventID) {
            return 0;
        }
        $event = OrgEvent::get()->byID($eventID);
        return $event && in_array((int) $event->OrganizationID, array_map('intval', $orgIDs), true) ? $event->ID : 0;
    }

    /**
     * Events der eigenen Organisationen (fürs Auswahlfeld beim Bearbeiten eines Termins)
     * GET  /api/v1/calendar/orgEvents
     * POST /api/v1/calendar/orgEvents  Body: { title, organizationId, …siehe OrgEvent::applyApiData() } — braucht CALENDAR_MANAGE
     */
    public function orgEvents(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = $member->getOrganizationIDs();

        if ($request->httpMethod() === 'POST') {
            $body  = json_decode($request->getBody(), true) ?? [];
            $orgID = (int) ($body['organizationId'] ?? 0);
            $org = Organization::get()->byID($orgID);
            if (!$org || !in_array($orgID, $orgIDs) || !$member->hasOrgPermission($org, OrgPermissions::CALENDAR_MANAGE)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }
            $event = OrgEvent::create(['OrganizationID' => $orgID]);
            $error = $event->applyApiData(array_merge(['title' => ''], $body));
            if ($error) {
                return $this->errorResponse($error, 400);
            }
            $event->write();
            return $this->successResponse(['event' => $event->toApiSummary($member)], 'Event angelegt');
        }

        $events = [];
        if (!empty($orgIDs)) {
            foreach (OrgEvent::get()->filter('OrganizationID', $orgIDs) as $event) {
                $events[] = $event->toApiSummary($member);
            }
        }
        return $this->jsonResponse(['events' => $events]);
    }

    /**
     * Ein einzelnes Event — per ID oder URL-Segment (Event-Seite /app/events/{URLSegment})
     * GET    /api/v1/calendar/orgEvent/{id|segment}  — auch ohne Anmeldung, wenn das Event öffentlich ist.
     *        Mitglieder der Organisation bekommen zusätzlich die Termine (isInternal: true).
     * PUT    /api/v1/calendar/orgEvent/{id}  Body: siehe OrgEvent::applyApiData() — braucht CALENDAR_MANAGE
     * DELETE /api/v1/calendar/orgEvent/{id}  — braucht CALENDAR_MANAGE; Termine bleiben erhalten
     */
    public function orgEvent(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        $method = $request->httpMethod();
        if (!$member && $method !== 'GET') {
            return $this->errorResponse('Unauthorized', 401);
        }

        $key = (string) $request->param('ID');
        $event = ctype_digit($key)
            ? OrgEvent::get()->byID((int) $key)
            : ($key !== '' ? OrgEvent::get()->filter('URLSegment', $key)->first() : null);
        // Nicht öffentliche Events gibt es für Außenstehende schlicht nicht
        if (!$event || !$event->isViewableBy($member)) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }

        if ($method === 'PUT' || $method === 'DELETE') {
            if (!$event->canBeManagedBy($member)) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }
            if ($method === 'DELETE') {
                $event->delete();
                return $this->successResponse([], 'Event gelöscht');
            }
            $body  = json_decode($request->getBody(), true) ?? [];
            $error = $event->applyApiData($body);
            if ($error) {
                return $this->errorResponse($error, 400);
            }
            $event->write();
            return $this->successResponse(['event' => $event->toApiSummary($member)], 'Event gespeichert');
        }

        if (!$event->isInternalFor($member)) {
            return $this->jsonResponse([
                'isInternal' => false,
                'event'      => array_merge($event->toApiPublic(), $event->interestToApi($member)),
            ]);
        }

        return $this->jsonResponse([
            'isInternal'   => true,
            'event'        => $event->toApiSummary($member),
            'appointments' => $event->appointmentsToApi($member),
        ]);
    }

    /**
     * "Interessiert" / "Ich bin dabei" setzen — jede angemeldete Person, die das Event sehen darf
     * POST /api/v1/calendar/orgEventInterest/{id}  Body: { type: 'Interested' | 'Going' | null }
     * (null entfernt die Markierung). Antwort: { InterestedCount, GoingCount, UserInterest }
     */
    public function orgEventInterest(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $event = OrgEvent::get()->byID((int) $request->param('ID'));
        if (!$event || !$event->isViewableBy($member)) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }

        $body = json_decode($request->getBody() ?: '[]', true) ?? [];
        $type = $body['type'] ?? null;
        if ($type !== null && !in_array($type, [OrgEventInterest::TYPE_INTERESTED, OrgEventInterest::TYPE_GOING], true)) {
            return $this->errorResponse('Ungültige Markierung', 400);
        }

        $interest = $event->Interests()->filter('MemberID', $member->ID)->first();
        if ($type === null) {
            $interest?->delete();
        } else {
            $interest ??= OrgEventInterest::create(['EventID' => $event->ID, 'MemberID' => $member->ID]);
            $interest->Type = $type;
            $interest->write();
        }

        return $this->successResponse($event->interestToApi($member), $type ? 'Markierung gespeichert' : 'Markierung entfernt');
    }

    /**
     * Events, die man als "Interessiert" oder "Ich bin dabei" markiert hat (Dashboard, Profil)
     * GET /api/v1/calendar/myOrgEvents — nach Beginn sortiert, ohne Datum am Ende.
     * Mitglieder der Organisation bekommen die Summary, alle anderen die öffentlichen Daten.
     */
    public function myOrgEvents(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $events = [];
        foreach (OrgEventInterest::get()->filter('MemberID', $member->ID) as $interest) {
            $event = $interest->Event();
            // Inzwischen nicht mehr öffentlich (oder gelöscht) — dann nicht anzeigen
            if (!$event || !$event->exists() || !$event->isViewableBy($member)) {
                continue;
            }
            $events[] = $event->isInternalFor($member)
                ? $event->toApiSummary($member)
                : array_merge($event->toApiPublic(), $event->interestToApi($member));
        }
        usort($events, fn ($a, $b) => [$a['RangeStart'] === null, $a['RangeStart'] ?? ''] <=> [$b['RangeStart'] === null, $b['RangeStart'] ?? '']);

        return $this->jsonResponse(['events' => $events]);
    }

    /**
     * Galerie eines Events (zusätzlich zum Hauptbild) — braucht CALENDAR_MANAGE
     * POST   /api/v1/calendar/orgEventGallery/{id}            multipart: images[]
     * DELETE /api/v1/calendar/orgEventGallery/{id}?image={ID}
     */
    public function orgEventGallery(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $event = OrgEvent::get()->byID((int) $request->param('ID'));
        if (!$event || !$event->isInternalFor($member)) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        if (!$event->canBeManagedBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        if ($request->httpMethod() === 'DELETE') {
            $image = $event->Images()->byID((int) $request->getVar('image'));
            if (!$image) {
                return $this->errorResponse('Bild nicht gefunden', 404);
            }
            $event->Images()->removeByID($image->ID);
            $image->deleteFile();
            $image->doArchive();
            return $this->successResponse(['event' => $event->toApiSummary($member)], 'Bild entfernt');
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $errors = $this->attachUploadedFiles([$event], 'EventImages/' . $event->ID . '/Galerie');
        if ($errors) {
            return $this->jsonResponse([
                'success' => false,
                'error'   => implode(' ', $errors),
                'data'    => ['event' => $event->toApiSummary($member)],
            ], 400);
        }
        return $this->successResponse(['event' => $event->toApiSummary($member)], 'Bilder hochgeladen');
    }

    /**
     * Auswahllisten fürs Event-Formular (im CMS gepflegt)
     * GET /api/v1/calendar/orgEventOptions
     */
    public function orgEventOptions(HTTPRequest $request): HTTPResponse
    {
        if (!$this->requireAuth()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        return $this->jsonResponse([
            'types'     => array_map(fn ($type) => $type->toApi(), OrgEventType::get()->toArray()),
            'ageGroups' => array_map(fn ($group) => $group->toApi(), OrgEventAgeGroup::get()->toArray()),
        ]);
    }

    /**
     * Bild eines Events — wird im Frontend im Format 16:9 zugeschnitten (ImageCropModal)
     * POST   /api/v1/calendar/orgEventImage/{id}  multipart: image (JPEG) — braucht CALENDAR_MANAGE
     * DELETE /api/v1/calendar/orgEventImage/{id}  — braucht CALENDAR_MANAGE
     */
    public function orgEventImage(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $event = OrgEvent::get()->byID((int) $request->param('ID'));
        if (!$event || !in_array((int) $event->OrganizationID, array_map('intval', $member->getOrganizationIDs()), true)) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        if (!$event->canBeManagedBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $method = $request->httpMethod();
        if ($method !== 'POST' && $method !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $file = null;
        $mime = null;
        if ($method === 'POST') {
            $file = $_FILES['image'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                return $this->errorResponse('Keine Datei hochgeladen');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                return $this->errorResponse('Die Datei darf maximal 2 MB groß sein');
            }
            // Der Cropper liefert das Ergebnis immer als JPEG
            $mime = mime_content_type($file['tmp_name']);
            if ($mime !== 'image/jpeg') {
                return $this->errorResponse('Nur JPEG wird akzeptiert');
            }
        }

        $newImageID = 0;
        if ($method === 'POST') {
            $image  = Image::create();
            $upload = Upload::create();
            $upload->getValidator()->setAllowedExtensions(['jpg', 'jpeg']);
            $upload->getValidator()->setAllowedMaxFileSize(2 * 1024 * 1024);

            $result = $upload->loadIntoFile([
                'name'     => 'Event.jpg',
                'type'     => $mime,
                'tmp_name' => $file['tmp_name'],
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file['size'],
            ], $image, 'EventImages/' . $event->ID);

            if (!$result) {
                $errors = $upload->getErrors();
                return $this->errorResponse(
                    !empty($errors) ? implode(', ', $errors) : 'Bild konnte nicht gespeichert werden'
                );
            }

            $image->write();
            $image->publishSingle();
            $newImageID = $image->ID;
        }

        // Altes Bild erst entfernen, wenn das neue sicher gespeichert ist
        if ($event->ImageID && $event->Image()->exists()) {
            $oldImage = $event->Image();
            $oldImage->deleteFile();
            $oldImage->doArchive();
        }
        $event->ImageID = $newImageID;
        $event->write();
        return $this->successResponse(
            ['ImageURL' => $event->RenderImage(), 'event' => $event->toApiSummary($member)],
            $method === 'POST' ? 'Bild gespeichert' : 'Bild entfernt'
        );
    }

    public function appointmentTypes(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $types = AppointmentType::get()->sort('Title ASC');
        $data  = [];
        foreach ($types as $type) {
            $data[] = [
                'ID'    => $type->ID,
                'Title' => $type->Title,
            ];
        }

        return $this->jsonResponse(['types' => $data]);
    }
}
