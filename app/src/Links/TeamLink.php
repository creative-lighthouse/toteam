<?php

namespace App\Links;

use Override;
use App\Teams\Department;
use App\Teams\Organization;
use SilverStripe\ORM\DataObject;
use SilverStripe\LinkField\Models\Link;

/**
 * Class \App\Links\TeamLink
 *
 * @property ?string $Title
 * @property int $SortOrder
 * @property int $ParentID
 * @property int $ButtonID
 * @property int $TypeID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\LinkField\Models\Link Button()
 * @method \App\Links\TeamLinkType Type()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
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
        "Parent" => Organization::class,
        "Button" => Link::class,
        "Type" => TeamLinkType::class,
    ];

    private static $owns = [
        'Button',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Button" => "Link",
        "Type" => "Link-Typ",
        "SortOrder" => "Sortierreihenfolge",
        "Parent" => "Organisation",
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
