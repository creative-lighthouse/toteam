<?php

namespace App\Teams;

use App\Calendar\Appointment;
use App\Food\Food;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Teams\OrgEvent
 *
 * Ein Event einer Organisation (z.B. "Halloweenhaus 2026"), das mehrere Termine
 * bündelt — Aufbau, Shows, Abbau. Vorerst dient es vor allem dem Essen: Gerichte
 * werden für ein Event vorgeschlagen und vom Essensplaner dann einer Mahlzeit
 * eines seiner Termine zugeordnet. Ein eigenes Events-Totem folgt später.
 *
 * (Nicht zu verwechseln mit den älteren EventDay-Klassen in App\Events oder den
 * Kalender-"Events" im Frontend, die einzelne Termine meinen.)
 *
 * @property ?string $Title
 * @property int $OrganizationID
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\ORM\DataList|\App\Calendar\Appointment[] Appointments()
 * @method \SilverStripe\ORM\DataList|\App\Food\Food[] Foods()
 */
class OrgEvent extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
    ];

    private static $has_many = [
        "Appointments" => Appointment::class . '.Event',
        "Foods"        => Food::class . '.Event',
    ];

    private static $default_sort = "Title ASC";

    private static $field_labels = [
        "Title"        => "Titel",
        "Organization" => "Organisation",
        "Appointments" => "Termine",
        "Foods"        => "Gerichte",
    ];

    private static $summary_fields = [
        "Title"              => "Titel",
        "Organization.Title" => "Organisation",
    ];

    private static $table_name = 'OrgEvent';
    private static $singular_name = "Event";
    private static $plural_name = "Events";

    /** Termine anlegen/zuordnen darf, wer in der Organisation Termine verwalten darf */
    public function canBeManagedBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::CALENDAR_MANAGE);
    }

    public function toApi(): array
    {
        $org = $this->Organization();
        return [
            'ID'                  => $this->ID,
            'Title'               => $this->Title,
            'OrganizationID'      => (int) $this->OrganizationID,
            'OrganizationTitle'   => $org->exists() ? $org->Title : null,
            'OrganizationLogoURL' => $org->exists() ? $org->RenderLogo(80) : null,
        ];
    }

    /** toApi() plus Terminanzahl, Zeitraum und Verwaltungsrecht — für die Events-Übersicht */
    public function toApiSummary(Member $member): array
    {
        $appointments = $this->Appointments();
        $first = $appointments->sort('DateStart', 'ASC')->first();
        $dateEnd = null;
        foreach ($appointments as $appointment) {
            $end = $appointment->DateEnd ?: $appointment->DateStart;
            if ($end && (!$dateEnd || $end > $dateEnd)) {
                $dateEnd = $end;
            }
        }
        return array_merge($this->toApi(), [
            'AppointmentCount' => $appointments->count(),
            'DateStart'        => $first ? $first->DateStart : null,
            'DateEnd'          => $dateEnd,
            'FoodCount'        => $this->Foods()->count(),
            'CanManage'        => $this->canBeManagedBy($member),
        ]);
    }

    /** Die Termine des Events, chronologisch, mit der Teilnahme des Mitglieds */
    public function appointmentsToApi(Member $member): array
    {
        $appointments = [];
        foreach ($this->Appointments()->sort(['DateStart' => 'ASC', 'TimeStart' => 'ASC']) as $appointment) {
            $participation = $appointment->Participations()->filter('MemberID', $member->ID)->first();
            $appointments[] = [
                'ID'        => $appointment->ID,
                'Title'     => $appointment->Title,
                'DateStart' => $appointment->DateStart,
                'DateEnd'   => $appointment->DateEnd ?: $appointment->DateStart,
                'TimeStart' => $appointment->TimeStart,
                'TimeEnd'   => $appointment->TimeEnd,
                'AllDay'    => (bool) $appointment->AllDay,
                'Location'  => $appointment->Location ?: null,
                'Status'    => $appointment->Status,
                'EventType' => $appointment->Type()->exists() ? $appointment->Type()->Title : null,
                'UserResponse' => $participation ? $participation->Type : null,
            ];
        }
        return $appointments;
    }

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        // has_one-Verweise werden nicht automatisch gelöst
        foreach ($this->Appointments() as $appointment) {
            $appointment->EventID = 0;
            $appointment->write();
        }
        foreach ($this->Foods() as $food) {
            $food->EventID = 0;
            $food->write();
        }
    }
}
