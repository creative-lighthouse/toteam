<?php

namespace App\Calendar;

use App\Teams\Organization;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Calendar\Absence
 *
 * @property ?string $DateStart
 * @property ?string $DateEnd
 * @property ?string $Recurrence
 * @property ?string $Note
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\Organization[] Organisations()
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

    private static $many_many = [
        'Organisations' => Organization::class,
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
     */
    public function appliesToDate(string $date): bool
    {
        $start = strtotime($this->DateStart);
        $end   = $this->DateEnd ? strtotime($this->DateEnd) : $start;
        $check = strtotime($date);

        if ($check < $start) {
            return false;
        }

        switch ($this->Recurrence) {
            case 'Never':
                return $check <= $end;

            case 'Daily':
                return true;

            case 'Weekly':
                return date('N', $check) === date('N', $start);

            case 'Monthly':
                return date('d', $check) === date('d', $start);

            case 'Yearly':
                return date('m-d', $check) === date('m-d', $start);
        }

        return false;
    }
}
