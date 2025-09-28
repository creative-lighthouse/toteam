<?php

namespace App\Pages;

use Calendar;
use PageController;
use App\Events\EventDay;
use App\Events\EventDayMeal;
use App\Events\EventDayMealEater;
use SilverStripe\Security\Security;
use App\Events\EventDayParticipation;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Control\Middleware\ConfirmationMiddleware\GetParameter;

class ParticipationPageController extends PageController
{
    private static $allowed_actions = [
        "changeParticipation",
        "changeParticipationFood",
        "changeParticipationTime",
    ];

    public function index()
    {
        //Check if there is a date param. Else use current date and redirect
        $date = $this->request->getVar('date');
        $eventday = $this->request->getVar('eventID');

        return [
            'Calendar' => DBField::create_field('HTMLText', $this->RenderCalendar($date, $eventday) ?? null),
            'Date' => $date,
        ];
    }

    public function changeParticipation($request)
    {
        $dateID = $request->param('ID');
        //Get new type of participation with POST
        $type = $this->getRequest()->postVar('response'); // This gets the response value from the form
        if (!$type || !in_array($type, ['Accept', 'Maybe', 'Decline'])) {
            return $this->httpError(400, 'Invalid participation type: ' . $type);
        }

        $eventday = EventDay::get_by_id($dateID);
        $member = Security::getCurrentUser();
        $eventDayParticipation = $eventday->Participations()->filter(['MemberID' => $member->ID])->first();
        if (!$eventDayParticipation) {
            $eventDayParticipation = EventDayParticipation::create();
            $eventDayParticipation->ParentID = $eventday->ID;
            $eventDayParticipation->MemberID = $member->ID;
            $eventDayParticipation->Type = $type;
            if($type == 'Accept') {
                $eventDayParticipation->TimeStart = $eventday->TimeStart;
                $eventDayParticipation->TimeEnd = $eventday->TimeEnd;
            }
            $eventDayParticipation->write();
        } else {
            if($type == 'Accept') {
                if(!$eventDayParticipation->TimeStart) {
                    $eventDayParticipation->TimeStart = $eventday->TimeStart;
                }
                if(!$eventDayParticipation->TimeEnd) {
                    $eventDayParticipation->TimeEnd = $eventday->TimeEnd;
                }
            }
            $eventDayParticipation->Type = $type;
            $eventDayParticipation->write();
        }

        $this->redirect($this->Link() . "?date=" . $eventday->Date . "&eventID=" . $eventday->ID);
    }

    public function changeParticipationFood($request)
    {
        $dateID = $request->param('ID');
        //Get new type of participation with POST
        $type = $this->getRequest()->postVar('response'); // This gets the response value from the form
        $mealID = $this->getRequest()->postVar('meal');
        if (!$type || !in_array($type, ['Accept', 'Decline'])) {
            return $this->httpError(400, 'Invalid participation type: ' . $type);
        }

        $meal = EventDayMeal::get_by_id($mealID);
        $member = Security::getCurrentUser();
        $eventday = EventDay::get_by_id($dateID);
        $eventDayMealEater = $meal->Eaters()->filter(['MemberID' => $member->ID])->first();

        if (!$eventDayMealEater) {
            $eventDayMealEater = EventDayMealEater::create();
            $eventDayMealEater->ParentID = $meal->ID;
            $eventDayMealEater->MemberID = $member->ID;
            $eventDayMealEater->Type = $type;

            $eventDayMealEater->write();
        } else {
            $eventDayMealEater->Type = $type;
            $eventDayMealEater->write();
        }

        $this->redirect($this->Link() . "?date=" . $eventday->Date . "&eventID=" . $eventday->ID);
    }

    public function changeParticipationTime($request)
    {
        $dateID = $request->param('ID');
        //Get new type of participation with POST
        $timestart = $this->getRequest()->postVar('timestart'); // This gets the response value from the form
        $timeend = $this->getRequest()->postVar('timeend'); // This gets the response value from the form
        if (!$timestart || !$timeend) {
            return $this->httpError(400, 'Invalid time input: ' . $timestart . ' - ' . $timeend);
        }
        echo "Change Participation Time to " . $timestart . " - " . $timeend . " for dateID " . $dateID;

        $eventday = EventDay::get_by_id($dateID);
        $member = Security::getCurrentUser();
        $eventDayParticipation = $eventday->Participations()->filter(['MemberID' => $member->ID])->first();
        if ($eventDayParticipation) {
            $eventDayParticipation->TimeStart = $timestart;
            $eventDayParticipation->TimeEnd = $timeend;
            $eventDayParticipation->write();
        } else {
            return $this->httpError(400, 'No participation found for user: ' . $member->ID);
        }

        $this->redirect($this->Link() . "?date=" . $eventday->Date . "&eventID=" . $eventday->ID);
    }

    public function RenderCalendar($date = null, $eventdayID = null)
    {
        $calendar = new Calendar($date, Security::getCurrentUser(), $eventdayID);
        return $calendar->render();
    }

    public function getLinkToNextMonth()
    {
        $date = $this->request->getVar('date');
        if (!$date) {
            $date = date('Y-m-d');
        }
        $nextmonth = date('Y-m-d', strtotime("+1 month", strtotime((string) $date)));
        return $this->Link() . "?date=" . $nextmonth;
    }

    public function getLinkToPreviousMonth()
    {
        $date = $this->request->getVar('date');
        if (!$date) {
            $date = date('Y-m-d');
        }
        $previousmonth = date('Y-m-d', strtotime("-1 month", strtotime((string) $date)));
        return $this->Link() . "?date=" . $previousmonth;
    }

    public function getCurrentMonthTitle()
    {
        $date = $this->request->getVar('date');
        if (!$date) {
            $date = date('Y-m-d');
        }
        return date('F Y', strtotime((string) $date));
    }
}
