<?php

namespace App\Maps;

use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use App\Teams\Organization;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use App\Notifications\PushNotificationService;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;

/**
 * Class \App\Maps\MapLayer
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property bool $Active
 * @property ?string $LayerColor
 * @property int $ParentID
 * @property int $ImageID
 * @method \App\Maps\Map Parent()
 * @method \SilverStripe\Assets\Image Image()
 * @method \SilverStripe\ORM\DataList|\App\Maps\MapPOI[] POIs()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MapLayer extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "Active" => "Boolean",
        "LayerColor" => "Varchar(7)",
    ];

    private static $has_one = [
        "Parent" => Map::class,
        "Image" => Image::class,
    ];

    private static $has_many = [
        "POIs" => MapPOI::class,
    ];

    private static $owns = [
        'Image',
        'POIs',
    ];

    private static $defaults = [
        "Active" => true,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "Active" => "Aktiv",
        "Image" => "Bild",
        "POIs" => "Marker",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Active.Nice" => "Aktiv",
        "POIs.Count" => "Anzahl Marker",
    ];

    private static $table_name = 'MapLayer';
    private static $singular_name = "Lageplan-Ebene";
    private static $plural_name = "Lageplan-Ebenen";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ParentID');

        //Move gridfield for pois in main tab
        $poisField = $fields->dataFieldByName('POIs');
        if ($poisField) {
            $poisfieldConfig = GridFieldConfig_RecordEditor::create();
            $poisField->setConfig($poisfieldConfig);
            $fields->removeByName('POIs');
            $fields->addFieldToTab('Root.Main', $poisField);
        }

        //Set type of layercolor to colorpicker
        $layerColorField = $fields->dataFieldByName('LayerColor');
        if ($layerColorField) {
            $layerColorField->setAttribute('type', 'color');
        }
        return $fields;
    }

    public function getCoordinatesUL()
    {
        if($this->CoordinatesUpperLeft) {
            return $this->CoordinatesUpperLeft;
        } else {
            if($this->Parent() && $this->Parent()->CoordinatesUpperLeft) {
                return $this->Parent()->CoordinatesUpperLeft;
            } else {
                return "0,0";
            }
        }
    }

    public function getCoordinatesLR()
    {
        if($this->CoordinatesLowerRight) {
            return $this->CoordinatesLowerRight;
        } else {
            if($this->Parent() && $this->Parent()->CoordinatesLowerRight) {
                return $this->Parent()->CoordinatesLowerRight;
            } else {
                return "0,0";
            }
        }
    }
}
