<?php

namespace App\Maps;

use App\Maps\MapLayer;
use App\Rooms\Room;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * Class \App\Maps\MapPOI
 *
 * @property ?string $Title
 * @property ?string $MarkerText
 * @property ?string $Description
 * @property bool $Active
 * @property ?string $Coordinates
 * @property ?string $Type
 * @property int $ParentID
 * @property int $RoomID
 * @method \App\Maps\MapLayer Parent()
 * @method \App\Rooms\Room Room()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
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
        "Type" => "Enum('marker,room', 'marker')",
    ];

    private static $has_one = [
        "Parent" => MapLayer::class,
        "Room" => Room::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "Active" => "Aktiv",
        "Coordinates" => "Koordinaten",
        "Type" => "Markertyp",
        "Room" => "Raum",
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

        if ($this->Type === 'room') {
            $room = $this->Room();
            if ($room && $room->exists() && $room->Title) {
                return mb_strtoupper(mb_substr($room->Title, 0, 1));
            }
        }

        return "42";
    }

    public function getMarkerColor()
    {
        return $this->Parent()->LayerColor;
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MAPLAYERS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_MAPLAYERS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MAPS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MAPLAYERS');
    }
}
