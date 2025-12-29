<?php

namespace App\Maps;

use App\Maps\MapLayer;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\Maps\MapPOI
 *
 * @property ?string $Title
 * @property ?string $MarkerText
 * @property ?string $Description
 * @property bool $Active
 * @property ?string $Coordinates
 * @property int $ParentID
 * @method \App\Maps\MapLayer Parent()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MapPOI extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "MarkerText" => "Varchar(4)",
        "Description" => "Text",
        "Active" => "Boolean",
        "Coordinates" => "Varchar(100)",
    ];

    private static $has_one = [
        "Parent" => MapLayer::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "Active" => "Aktiv",
        "Coordinates" => "Koordinaten",
    ];

    private static $defaults = [
        "Active" => true,
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Active.Nice" => "Aktiv",
    ];

    private static $table_name = 'MapPOI';
    private static $singular_name = "POI";
    private static $plural_name = "POIs";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ParentID');
        return $fields;
    }

    public function getDetailInfo()
    {
        return $this->Description;
    }

    public function getMarkerText()
    {
        $markerText = $this->getField('MarkerText');
        if(!empty($markerText)) {
            return $markerText;
        }
        return "42";
    }

    public function getMarkerColor()
    {
        return $this->Parent()->LayerColor;
    }
}
