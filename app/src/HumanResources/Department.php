<?php

namespace App\HumanResources;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\HumanResources\Department
 *
 * @property ?string $Title
 * @property int $ImageID
 * @method \SilverStripe\Assets\Image Image()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Department extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
    ];

    private static $has_one = [
        "Image" => Image::class,
    ];

    private static $owns = [
        'Image',
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'Department';
    private static $singular_name = "Arbeits-Bereich";
    private static $plural_name = "Arbeits-Bereiche";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
