<?php

namespace App\HumanResources;

use Override;
use App\Food\Food;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\HumanResources\Allergy
 *
 * @property ?string $Title
 * @property int $SortOrder
 * @property int $IconID
 * @method \SilverStripe\Assets\Image Icon()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Members()
 * @method \SilverStripe\ORM\ManyManyList|\App\Food\Food[] Foods()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Allergy extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "SortOrder" => "Int",
    ];

    private static $has_one = [
        "Icon" => Image::class,
    ];

    private static $owns = [
        'Icon',
    ];

    private static $many_many = [
        "Members" => Member::class,
    ];

    private static $belongs_many_many = [
        'Foods' => Food::class,
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title"
    ];

    private static $table_name = 'Allergy';
    private static $singular_name = "Allergie";
    private static $plural_name = "Allergien";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function getIsInFood($foodid)
    {
        $foods = $this->Foods()->filter('ID', $foodid);
        if ($foods->count() > 0) {
            return true;
        }
        return false;
    }
}
