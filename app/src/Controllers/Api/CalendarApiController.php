<?php

namespace App\Controllers\Api;

use App\Calendar\Appointment;
use App\Calendar\AppointmentParticipation;
use App\Food\Meal;
use App\Food\MealEater;
use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;

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
        'participationFood'
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
                $participations[] = [
                    'ID' => $p->ID,
                    'MemberID' => $p->MemberID,
                    'MemberName' => $p->Member() ? $p->Member()->getName() : 'Unknown',
                    'Type' => $p->Type,
                    'TimeStart' => $p->TimeStart,
                    'TimeEnd' => $p->TimeEnd,
                    'IsCurrentUser' => $p->MemberID == $member->ID
                ];
            }

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
                'Type' => $appointment->Type()->exists() ? $appointment->Type()->Title : null,
                'ImageURL' => $appointment->Image()->exists() ? $appointment->Image()->getURL() : null,
                'UserParticipation' => $participation ? [
                    'ID' => $participation->ID,
                    'Type' => $participation->Type,
                    'TimeStart' => $participation->TimeStart,
                    'TimeEnd' => $participation->TimeEnd
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
            $participation->ParentID = $appointment->ID;
            $participation->MemberID = $member->ID;
            $participation->Type = $type;

            if ($type == 'Accept') {
                $participation->TimeStart = $appointment->TimeStart;
                $participation->TimeEnd = $appointment->TimeEnd;
            }
        } else {
            if ($type == 'Accept' || $type == 'Maybe') {
                if (!$participation->TimeStart) {
                    $participation->TimeStart = $appointment->TimeStart;
                }
                if (!$participation->TimeEnd) {
                    $participation->TimeEnd = $appointment->TimeEnd;
                }
            }
            $participation->Type = $type;
        }

        $participation->write();

        return $this->successResponse([
            'ID' => $participation->ID,
            'Type' => $participation->Type,
            'TimeStart' => $participation->TimeStart,
            'TimeEnd' => $participation->TimeEnd
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
        $timeend = $body['timeend'] ?? null;

        if (!$timestart || !$timeend) {
            return $this->errorResponse('Invalid time input', 400);
        }

        $participation = $appointment->Participations()->filter(['MemberID' => $member->ID])->first();

        if (!$participation) {
            return $this->errorResponse('No participation found', 404);
        }

        $participation->TimeStart = $timestart;
        $participation->TimeEnd = $timeend;
        $participation->write();

        return $this->successResponse([
            'TimeStart' => $participation->TimeStart,
            'TimeEnd' => $participation->TimeEnd
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
}
