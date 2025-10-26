<?php

namespace App\Links;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use App\HumanResources\Department;
use SilverStripe\LinkField\Models\Link;

/**
 * Class \App\HumanResources\TeamLink
 *
 * @property ?string $Title
 * @property int $SortOrder
 * @property int $ButtonID
 * @property int $TypeID
 * @method \SilverStripe\LinkField\Models\Link Button()
 * @method \App\Links\TeamLinkType Type()
 * @method \SilverStripe\ORM\ManyManyList|\App\HumanResources\Department[] Departments()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class TeamLink extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "SortOrder" => "Int",
    ];

    private static $has_one = [
        "Button" => Link::class,
        "Type" => TeamLinkType::class,
    ];

    private static $many_many = [
        "Departments" => Department::class,
    ];

    private static $owns = [
        'Button',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Button" => "Link",
        "Type" => "Link-Typ",
    ];

    private static $summary_fields = [
        "Title"
    ];

    private static $default_sort = 'SortOrder ASC';

    private static $table_name = 'TeamLink';
    private static $singular_name = "Link";
    private static $plural_name = "Links";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('SortOrder');
        return $fields;
    }
}
