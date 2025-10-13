<?php

namespace App\Events;

use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Events\EventDayMealEater
 *
 * @property ?string $Type
 * @property int $ParentID
 * @property int $MemberID
 * @method \App\Events\EventDayMeal Parent()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class EventDayMealEater extends DataObject
{
    private static $db = [
        "Type" => "Enum('Accept, Decline')",
    ];

    private static $has_one = [
        'Parent' => EventDayMeal::class,
        "Member" => Member::class
    ];

    private static $field_labels = [
        "Member" => "Benutzer",
        "Type" => "Nimmt teil",
    ];

    private static $summary_fields = [
        "Member.Title" => "Benutzer",
        "Type" => "Nimmt teil",
    ];

    private static $table_name = 'EventDayMealEater';
    private static $singular_name = "Mahlzeit-Teilnehmer";
    private static $plural_name = "Mahlzeit-Teilnehmer";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("ParentID");
        return $fields;
    }
}
