<?php

namespace App\HumanResources;

use Override;
use App\Links\TeamLink;
use App\Links\TeamDownload;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\HumanResources\Department
 *
 * @property ?string $Title
 * @property int $ImageID
 * @method \SilverStripe\Assets\Image Image()
 * @method \SilverStripe\ORM\ManyManyList|\App\Links\TeamLink[] TeamLinks()
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

    private static $belongs_many_many = [
        'TeamLinks' => TeamLink::class,
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
