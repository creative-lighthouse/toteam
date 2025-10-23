<?php

namespace App\Controllers;

use App\Events\EventDay;
use SilverStripe\Security\Member;
use App\Controllers\BaseController;
use SilverStripe\Control\HTTPResponse;
use App\Controllers\CalendarController;

/**
 * Class \App\Controllers\ICSController
 *
 */
class ICSController extends BaseController
{
    private static $url_segment = 'ics';

    private static $allowed_actions = [
    ];

    public function index()
    {
        $userHash = $this->request->getVar('user');
        $currentUser = Member::get()->filter('Hash', $userHash)->first();

        if (!$userHash || !$currentUser) {
            return $this->httpError(403, 'Access denied');
        }

        $icsContent = $this->generateICS($currentUser);

        $response = HTTPResponse::create($icsContent);
        $response->addHeader('Content-Type', 'text/calendar; charset=utf-8');
        $response->addHeader('Content-Disposition', 'inline; filename="toteam-events.ics"');

        return $response;
    }

    private function generateICS($user)
    {
        $eventDays = EventDay::get();

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//ToTeam//Creative Lighthouse//DE\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        foreach ($eventDays as $eventDay) {
            $eventdescription = $eventDay->Description;

            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:" . $eventDay->ID . "@toteam\r\n";
            $ics .= "SEQUENCE:" . $eventDay->ICSSequence . "\r\n";
            $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z', strtotime($eventDay->LastEdited)) . "\r\n";
            $ics .= "DTSTART:" . gmdate('Ymd\THis\Z', strtotime($eventDay->getFullStartDate())) . "\r\n";
            $ics .= "DTEND:" . gmdate('Ymd\THis\Z', strtotime($eventDay->getFullEndDate())) . "\r\n";
            $ics .= "LAST-MODIFIED:" . gmdate('Ymd\THis\Z', strtotime($eventDay->LastEdited)) . "\r\n";
            $ics .= "SUMMARY:" . $eventDay->Title . "\r\n";
            $ics .= "DESCRIPTION:" . ($eventdescription ? $eventdescription . "\\n" : "") . CalendarController::getUsersForDay($eventDay->ID) . "\\n" . CalendarController::getFoodForDay($eventDay->ID, $user->ID) . "\r\n";
            $ics .= "LOCATION:" . $eventDay->Location . "\r\n";
            $ics .= "CLASS:PUBLIC\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }
}
