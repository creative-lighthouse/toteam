<?php

namespace App\Controllers;

use App\Calendar\Appointment;
use SilverStripe\Security\Member;
use App\Controllers\BaseController;
use SilverStripe\Control\HTTPResponse;

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
        $response->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->addHeader('Pragma', 'no-cache');

        return $response;
    }

    private function generateICS($user)
    {
        $organizationIDs = $user->getOrganizationIDs();

        if (empty($organizationIDs)) {
            $appointments = Appointment::get()->filter('ID', 0);
        } else {
            $appointments = Appointment::get()
                ->exclude('Status', 'Cancelled')
                ->filter('Organisations.ID', $organizationIDs)
                ->distinct(true);
        }

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//ToTeam//Creative Lighthouse//DE\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "REFRESH-INTERVAL;VALUE=DURATION:PT15M\r\n";
        $ics .= "X-PUBLISHED-TTL:PT15M\r\n";
        $ics .= "X-WR-CALNAME:ToTeam\r\n";

        foreach ($appointments as $appointment) {
            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:appointment-" . $appointment->ID . "@toteam\r\n";
            $ics .= "SEQUENCE:" . $appointment->ICSSequence . "\r\n";
            $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z', strtotime($appointment->LastEdited)) . "\r\n";
            $ics .= "LAST-MODIFIED:" . gmdate('Ymd\THis\Z', strtotime($appointment->LastEdited)) . "\r\n";

            if ($appointment->AllDay) {
                $ics .= "DTSTART;VALUE=DATE:" . date('Ymd', strtotime($appointment->DateStart)) . "\r\n";
                $endDate = $appointment->DateEnd ?: $appointment->DateStart;
                // ICS all-day DTEND is exclusive, so add one day
                $ics .= "DTEND;VALUE=DATE:" . date('Ymd', strtotime($endDate . ' +1 day')) . "\r\n";
            } else {
                $ics .= "DTSTART:" . gmdate('Ymd\THis\Z', strtotime($appointment->getFullStartDate())) . "\r\n";
                $ics .= "DTEND:" . gmdate('Ymd\THis\Z', strtotime($appointment->getFullEndDate())) . "\r\n";
            }

            $ics .= $this->foldLine("SUMMARY:" . $this->escapeICS($appointment->Title)) . "\r\n";
            $ics .= $this->foldLine("LOCATION:" . $this->escapeICS($appointment->Location)) . "\r\n";

            $description = $appointment->Description ? $this->escapeICS($appointment->Description) . "\\n\\n" : "";
            $description .= $this->getParticipantsDescription($appointment);
            $description .= $this->getFoodDescription($appointment, $user);
            if ($description) {
                $ics .= $this->foldLine("DESCRIPTION:" . $description) . "\r\n";
            }

            $ics .= "CLASS:PUBLIC\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    private function foldLine(string $line): string
    {
        $result = '';
        while (strlen($line) > 75) {
            $result .= substr($line, 0, 75) . "\r\n ";
            $line = substr($line, 75);
        }
        return $result . $line;
    }

    private function getParticipantsDescription(Appointment $appointment): string
    {
        $participations = $appointment->Participations();
        if (!$participations || $participations->count() === 0) {
            return "";
        }

        $groups = ['Accept' => '✓ Zugesagt', 'Maybe' => '? Vielleicht', 'Decline' => '✗ Abgesagt'];
        $lines = ["=== Teilnehmer ==="];
        foreach ($groups as $type => $label) {
            $names = [];
            foreach ($participations->filter('Type', $type) as $p) {
                $member = $p->Member();
                if ($member && $member->exists()) {
                    $names[] = $member->getName();
                }
            }
            if (!empty($names)) {
                $lines[] = $label . ": " . implode(", ", $names);
            }
        }

        if (count($lines) === 1) {
            return "";
        }

        return implode("\\n", $lines) . "\\n\\n";
    }

    private function getFoodDescription(Appointment $appointment, $user): string
    {
        $meals = $appointment->Meals();
        if (!$meals || $meals->count() === 0) {
            return "";
        }

        $lines = ["=== Mahlzeiten ==="];
        foreach ($meals as $meal) {
            $lines[] = $meal->Title . " (" . $meal->RenderTime() . "):";
            foreach ($meal->Eaters() as $eater) {
                $member = $eater->Member();
                if ($member) {
                    $lines[] = "- " . $member->getName() . " (" . $eater->Type . ")";
                }
            }
        }

        return implode("\\n", $lines);
    }

    private function escapeICS(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return str_replace(["\r\n", "\r", "\n", ',', ';'], ['\\n', '\\n', '\\n', '\,', '\;'], $value);
    }
}
