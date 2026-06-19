<?php

namespace App\Controllers\Api;

use App\Calendar\Absence;
use App\Calendar\Appointment;
use App\Calendar\AppointmentParticipation;
use App\Calendar\AppointmentType;
use App\Food\Meal;
use App\Food\MealEater;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Calendar API Controller
 *
 */
class CalendarApiController extends ApiController
{
    private static $url_segment = 'api/v1/calendar';

    private static $allowed_actions = [
        'index',
        'participation',
        'participationTime',
        'participationFood',
        'participationNotes',
        'absence',
        'absences',
        'appointment',
        'appointmentTypes',
        'meal',
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

        $appointments = Appointment::get()
            ->filter([
                'Organisations.ID' => $organizationIDs,
                'DateStart:GreaterThanOrEqual' => $startDate,
                'DateStart:LessThanOrEqual' => $endDate
            ])
            ->distinct(true)
            ->sort('DateStart', 'ASC');

        $events = [];
        foreach ($appointments as $appointment) {
            $participation = $appointment->Participations()->filter(['MemberID' => $member->ID])->first();

            // Get meals
            $meals = [];
            foreach ($appointment->Meals() as $meal) {
                $mealEater = $meal->Eaters()->filter(['MemberID' => $member->ID])->first();
                $meals[] = [
                    'ID' => $meal->ID,
                    'Title' => $meal->Title,
                    'Time' => $meal->Time,
                    'RenderTime' => $meal->RenderTime(),
                    'UserResponse' => $mealEater ? $mealEater->Type : null
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
                    'ProfileImageURL' => $pMember && $pMember->hasMethod('RenderProfileImage')
                        ? $pMember->RenderProfileImage()
                        : null,
                    'Type'            => $p->Type,
                    'TimeStart'       => $p->TimeStart,
                    'TimeEnd'         => $p->TimeEnd,
                    'CustomTimeframe' => (bool) $p->CustomTimeframe,
                    'IsCurrentUser'   => $p->MemberID == $member->ID,
                    'Notes'           => $p->Notes ?: null,
                ];
            }

            // Organisation logos (all organisations)
            $orgLogos = [];
            foreach ($appointment->Organisations() as $orgItem) {
                if ($orgItem->Logo()->exists()) {
                    $orgLogos[] = [
                        'ID'     => $orgItem->ID,
                        'LogoURL' => $orgItem->Logo()->ScaleWidth(40)->getURL(),
                    ];
                }
            }
            $orgLogoURL = !empty($orgLogos) ? $orgLogos[0]['LogoURL'] : null;

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
                ] : null,
                'Meals' => $meals,
                'Participations' => $participations
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
     * Body: { response: "Accept|Maybe|Decline" }
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

        if (!$type || !in_array($type, ['Accept', 'Maybe', 'Decline'])) {
            return $this->errorResponse('Invalid participation type', 400);
        }

        // Find or create participation
        $participation = $appointment->Participations()->filter(['MemberID' => $member->ID])->first();

        if (!$participation) {
            $participation = AppointmentParticipation::create();
            $participation->ParentID        = $appointment->ID;
            $participation->MemberID        = $member->ID;
            $participation->CustomTimeframe = false;
        }

        $participation->Type = $type;
        $participation->write();

        return $this->successResponse([
            'ID'              => $participation->ID,
            'Type'            => $participation->Type,
            'TimeStart'       => $participation->TimeStart,
            'TimeEnd'         => $participation->TimeEnd,
            'CustomTimeframe' => (bool) $participation->CustomTimeframe,
            'Notes'           => $participation->Notes ?: null,
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
     * Body: { response: "Accept|Decline" }
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

        if (!$type || !in_array($type, ['Accept', 'Decline'])) {
            return $this->errorResponse('Invalid food response type', 400);
        }

        $mealEater = $meal->Eaters()->filter(['MemberID' => $member->ID])->first();

        if (!$mealEater) {
            $mealEater = MealEater::create();
            $mealEater->ParentID = $meal->ID;
            $mealEater->MemberID = $member->ID;
        }

        $mealEater->Type = $type;
        $mealEater->write();

        return $this->successResponse([
            'ID' => $mealEater->ID,
            'Type' => $mealEater->Type
        ], 'Food participation updated');
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
            $absence->Organisations()->removeAll();
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

            $absence->Organisations()->removeAll();
            $orgIDs = $body['organizationIds'] ?? [];
            if (!empty($orgIDs)) {
                $validIDs = $member->getOrganizationIDs();
                $filtered = array_values(array_intersect(array_map('intval', $orgIDs), $validIDs));
                if (!empty($filtered)) {
                    $absence->Organisations()->addMany($filtered);
                }
            }
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

        $orgIDs = $body['organizationIds'] ?? [];
        if (!empty($orgIDs)) {
            $validIDs = $member->getOrganizationIDs();
            $filtered = array_values(array_intersect(array_map('intval', $orgIDs), $validIDs));
            if (!empty($filtered)) {
                $absence->Organisations()->addMany($filtered);
            }
        }

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
                'OrganizationIds' => array_map('intval', $absence->Organisations()->column('ID')),
            ];
        }

        return $this->jsonResponse(['absences' => $result]);
    }

    /**
     * Create a new appointment (moderator/admin only).
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
            $hasPermission = OrganizationMembership::get()->filter([
                'MemberID'       => $member->ID,
                'OrganizationID' => $apptOrgIDs,
                'Role'           => ['moderator', 'admin'],
            ])->exists();
            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }
            $appt->Organisations()->removeAll();
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
            $hasPermission = OrganizationMembership::get()->filter([
                'MemberID'       => $member->ID,
                'OrganizationID' => $apptOrgIDs,
                'Role'           => ['moderator', 'admin'],
            ])->exists();
            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }
            $allDay = !empty($body['allDay']);
            $appt->Title       = trim($body['title'] ?? '') ?: $appt->Title;
            $appt->DateStart   = $body['dateStart'] ?? $appt->DateStart;
            $appt->DateEnd     = $body['dateEnd'] ?: ($body['dateStart'] ?? $appt->DateEnd);
            $appt->TimeStart   = $allDay ? null : ($body['timeStart'] ?: null);
            $appt->TimeEnd     = $allDay ? null : ($body['timeEnd'] ?: null);
            $appt->AllDay      = $allDay;
            $appt->Location    = $body['location'] ?? '';
            $appt->Description = $body['description'] ?? '';
            $appt->Status      = in_array($body['status'] ?? '', ['Suggested', 'Scheduled', 'Cancelled'])
                ? $body['status'] : $appt->Status;
            $appt->TypeID = !empty($body['typeId']) ? (int) $body['typeId'] : 0;
            $appt->write();
            $newOrgIDs = array_map('intval', $body['organizationIds'] ?? []);
            if (!empty($newOrgIDs)) {
                $appt->Organisations()->setByIDList($newOrgIDs);
            }
            return $this->successResponse(['ID' => $appt->ID], 'Termin aktualisiert');
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $orgIDs = array_map('intval', $body['organizationIds'] ?? []);

        if (empty($orgIDs)) {
            return $this->errorResponse('Mindestens eine Organisation muss gewählt werden', 400);
        }

        // Verify the user is moderator/admin in at least one of the chosen organisations
        $hasPermission = OrganizationMembership::get()->filter([
            'MemberID'       => $member->ID,
            'OrganizationID' => $orgIDs,
            'Role'           => ['moderator', 'admin'],
        ])->exists();

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

        $appt->write();
        $appt->Organisations()->addMany($orgIDs);

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
     * Create a meal for an appointment (moderator/admin only).
     * POST /api/v1/calendar/meal/:appointmentId
     * Body: { title, time } (time as HH:mm)
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

            $apptOrgIDs = $meal->Parent()->Organisations()->column('ID');
            $hasPermission = OrganizationMembership::get()->filter([
                'MemberID'       => $member->ID,
                'OrganizationID' => $apptOrgIDs,
                'Role'           => ['moderator', 'admin'],
            ])->exists();

            if (!$hasPermission) {
                return $this->errorResponse('Keine Berechtigung', 403);
            }

            $body  = $this->getJsonBody();
            $title = trim($body['title'] ?? '');
            $time  = trim($body['time'] ?? '');

            if (!$title) {
                return $this->errorResponse('Titel ist erforderlich', 400);
            }
            if (!$time) {
                return $this->errorResponse('Uhrzeit ist erforderlich', 400);
            }
            if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                $time .= ':00';
            }

            $meal->Title = $title;
            $meal->Time  = $time;
            $meal->write();

            return $this->successResponse([
                'ID'         => $meal->ID,
                'Title'      => $meal->Title,
                'Time'       => $meal->Time,
                'RenderTime' => $meal->RenderTime(),
            ], 'Mahlzeit aktualisiert');
        }

        // DELETE /api/v1/calendar/meal/:mealId — delete meal
        if ($method === 'DELETE') {
            $meal = Meal::get()->byID($id);
            if (!$meal || !$meal->exists()) {
                return $this->errorResponse('Mahlzeit nicht gefunden', 404);
            }

            $apptOrgIDs = $meal->Parent()->Organisations()->column('ID');
            $hasPermission = OrganizationMembership::get()->filter([
                'MemberID'       => $member->ID,
                'OrganizationID' => $apptOrgIDs,
                'Role'           => ['moderator', 'admin'],
            ])->exists();

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
        $hasPermission = OrganizationMembership::get()->filter([
            'MemberID'       => $member->ID,
            'OrganizationID' => $apptOrgIDs,
            'Role'           => ['moderator', 'admin'],
        ])->exists();

        if (!$hasPermission) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();

        $title = trim($body['title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
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
        $meal->write();

        return $this->successResponse([
            'ID'           => $meal->ID,
            'Title'        => $meal->Title,
            'Time'         => $meal->Time,
            'RenderTime'   => $meal->RenderTime(),
            'UserResponse' => null,
        ], 'Mahlzeit hinzugefügt');
    }

    /**
     * Fetch all Absence records visible to the given organisation IDs,
     * with their Organisations relation pre-filtered for the org-scope check.
     *
     * @param int[] $organizationIDs
     * @return \SilverStripe\ORM\DataList
     */
    private function getRelevantAbsences(array $organizationIDs)
    {
        $memberIDsInOrgs = OrganizationMembership::get()
            ->filter(['OrganizationID' => $organizationIDs, 'Role' => ['member', 'moderator', 'admin']])
            ->column('MemberID');

        $absences = Absence::get()->filter(['MemberID' => $memberIDsInOrgs]);

        // Filter out absences scoped to orgs the current user doesn't share
        $filtered = [];
        foreach ($absences as $absence) {
            $absenceOrgIDs = $absence->Organisations()->column('ID');
            if (!empty($absenceOrgIDs) && empty(array_intersect($absenceOrgIDs, $organizationIDs))) {
                continue;
            }
            $filtered[] = $absence;
        }

        return $filtered;
    }

    /**
     * List all appointment types.
     * GET /api/v1/calendar/appointmentTypes
     */
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
