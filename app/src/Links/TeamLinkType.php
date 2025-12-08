<?php

namespace App\Links;

use Override;
use SilverStripe\ORM\DataObject;
use App\HumanResources\Department;
use SilverStripe\LinkField\Models\Link;

/**
 * Class \App\Links\TeamLinkType
 *
 * @property ?string $Title
 * @property ?string $Description
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class TeamLinkType extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title"
    ];

    private static $default_sort = 'Title ASC';

    private static $table_name = 'TeamLinkType';
    private static $singular_name = "Link-Typ";
    private static $plural_name = "Link-Typen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
