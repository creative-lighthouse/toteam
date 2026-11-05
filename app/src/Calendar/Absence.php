<?php

namespace App\Calendar;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Calendar\Absence
 *
 * Gilt immer für alle Organisationen/Kalender, in denen das Mitglied ist —
 * eine Abwesenheit ist am Menschen festgemacht, nicht an einer Organisation.
 *
 * @property ?string $DateStart
 * @property ?string $DateEnd
 * @property ?string $Recurrence
 * @property ?string $Note
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Absence extends DataObject
{
    private static $table_name = 'Absence';

    private static $db = [
        'DateStart'  => 'Date',
        'DateEnd'    => 'Date',
        'Recurrence' => "Enum('Never,Daily,Weekly,Monthly,Yearly','Never')",
        'Note'       => 'Text',
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $singular_name = 'Abwesenheit';
    private static $plural_name   = 'Abwesenheiten';

    private static $summary_fields = [
        'Member.Name'      => 'Person',
        'DateStart'        => 'Von',
        'DateEnd'          => 'Bis',
        'RecurrenceNice'   => 'Wiederholung',
        'Note'             => 'Notiz',
    ];

    private static $recurrence_labels = [
        'Never'   => 'Nie',
        'Daily'   => 'Täglich',
        'Weekly'  => 'Wöchentlich',
        'Monthly' => 'Monatlich',
        'Yearly'  => 'Jährlich',
    ];

    public function getRecurrenceNice(): string
    {
        return self::$recurrence_labels[$this->Recurrence] ?? $this->Recurrence;
    }

    /**
     * Check whether this absence applies to the given date string (YYYY-MM-DD).
     *
     * DateStart–DateEnd is the first occurrence; recurring absences repeat that
     * whole span (e.g. weekly Mon–Wed covers every Mon, Tue and Wed).
     */
    public function appliesToDate(string $date): bool
    {
        $start = new \DateTimeImmutable(substr((string) $this->DateStart, 0, 10));
        $end   = $this->DateEnd ? new \DateTimeImmutable(substr((string) $this->DateEnd, 0, 10)) : $start;
        $check = new \DateTimeImmutable(substr($date, 0, 10));

        if ($check < $start) {
            return false;
        }
        if ($end < $start) {
            $end = $start;
        }

        if ($this->Recurrence === 'Never' || !$this->Recurrence) {
            return $check <= $end;
        }

        // Is there an occurrence starting within the last $duration days?
        $duration = min((int) $start->diff($end)->days, 366);
        for ($k = 0; $k <= $duration; $k++) {
            $candidate = $check->modify("-{$k} days");
            if ($candidate < $start) {
                break;
            }
            if ($this->isOccurrenceStart($start, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function isOccurrenceStart(\DateTimeImmutable $start, \DateTimeImmutable $candidate): bool
    {
        switch ($this->Recurrence) {
            case 'Daily':
                return true;
            case 'Weekly':
                return $candidate->format('N') === $start->format('N');
            case 'Monthly':
                return $candidate->format('d') === $start->format('d');
            case 'Yearly':
                return $candidate->format('m-d') === $start->format('m-d');
        }

        return false;
    }
}
