<?php

namespace App\Maps;

use App\Teams\Organization;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use App\Notifications\PushNotificationService;
use SilverStripe\Assets\Image;

class Map extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "ShortText" => "Text",
        "CoordinatesUpperLeft" => "Varchar(100)",
        "CoordinatesLowerRight" => "Varchar(100)",
        "Active" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
        "Author" => Member::class,
        "BackgroundImage" => Image::class,
    ];

    private static $has_many = [
        "MapLayers" => MapLayer::class,
    ];

    private static $owns = [
        "MapLayers",
        'BackgroundImage',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "ShortText" => "Kurztext",
        "Coordinates" => "Koordinaten",
        "ReleaseDate" => "Veröffentlichungsdatum",
        "ExpiryDate" => "Ablaufdatum",
        "Author" => "Autor",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Active.Nice" => "Aktiv",
    ];

    private static $table_name = 'Map';
    private static $singular_name = "Lageplan";
    private static $plural_name = "Lagepläne";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function getLink()
    {
        return '/maps/view/' . $this->ID;
    }

    /**
     * Send push notification for new notices
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Only send notification for newly created notices
        $changedFields = $this->getChangedFields(false, 1);
        $isNew = isset($changedFields['ID']) && empty($changedFields['ID']['before']);

        if ($isNew) {
            PushNotificationService::notifyNewMap($this);
        }
    }
}
