<?php

namespace App\Pages;

use Calendar;
use PageController;
use App\Events\EventDay;
use App\Events\EventDayParticipation;
use SilverStripe\Security\Security;
use SilverStripe\ORM\FieldType\DBField;

class ParticipationPageController extends PageController
{
    private static $allowed_actions = [
        "add",
        "change",
        "delete",
    ];

    public function index()
    {
        //Check if there is a date param. Else use current date and redirect
        $date = $this->request->getVar('date');
        if (!$date) {
            $this->redirect($this->Link() . "?date=" . date('Y-m-d'));
        }

        $eventdaysasjson = [];
        $allEventDays = EventDay::get()->sort('Date ASC');
        foreach ($allEventDays as $eventday) {
            $eventdaysasjson[] = $eventday->jsonSerialize();
        }

        return [
            'CalendarDataJSON' => json_encode($eventdaysasjson),
            'Date' => $date,
        ];
    }

    public function EventDays()
    {
        $member = Security::getCurrentUser();
        if ($member) {
            $days = EventDay::get()->sort('Date ASC');
            return $days;
        }
        return null;
    }
}
