<?php

namespace App\Food;

use Override;
use App\Food\Meal;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Food\MealEater
 *
 * @property ?string $Type
 * @property int $ParentID
 * @property int $MemberID
 * @method \App\Food\Meal Parent()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MealEater extends DataObject
{
    private static $db = [
        "Type" => "Enum('Accept, Decline')",
    ];

    private static $has_one = [
        'Parent' => Meal::class,
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

    private static $table_name = 'MealEater';
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
