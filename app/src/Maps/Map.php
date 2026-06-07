<?php

namespace App\Maps;

use App\Teams\Organization;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use App\Notifications\PushNotificationService;

/**
 * Class \App\Maps\Map
 *
 * @property ?string $Title
 * @property ?string $ShortText
 * @property ?string $CoordinatesUpperLeft
 * @property ?string $CoordinatesUpperRight
 * @property ?string $CoordinatesLowerLeft
 * @property ?string $CoordinatesLowerRight
 * @property bool $Active
 * @property int $ParentID
 * @property int $AuthorID
 * @property int $BackgroundImageID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\Security\Member Author()
 * @method \SilverStripe\Assets\Image BackgroundImage()
 * @method \SilverStripe\ORM\DataList|\App\Maps\MapLayer[] MapLayers()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Map extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "ShortText" => "Text",
        "CoordinatesUpperLeft" => "Varchar(100)",
        "CoordinatesUpperRight" => "Varchar(100)",
        "CoordinatesLowerLeft" => "Varchar(100)",
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

    private static $defaults = [
        "Active" => true,
    ];

    private static $owns = [
        "MapLayers",
        'BackgroundImage',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "ShortText" => "Kurztext",
        "CoordinatesUpperLeft" => "Koordinaten obere linke Ecke",
        "CoordinatesLowerRight" => "Koordinaten untere rechte Ecke",
        "CoordinatesUpperRight" => "Koordinaten obere rechte Ecke",
        "CoordinatesLowerLeft" => "Koordinaten untere linke Ecke",
        "ReleaseDate" => "Veröffentlichungsdatum",
        "ExpiryDate" => "Ablaufdatum",
        "Author" => "Autor",
        "Active" => "Aktiv",
        "BackgroundImage" => "Hintergrundbild",
        "Parent" => "Organisation",
    ];

    private static $summary_fields = [
        "BackgroundImage.CMSThumbnail" => "Hintergrundbild",
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

    public function providePermissions()
    {
        return [
            'CREATE_MAPS' => [
                'name' => 'Lagepläne erstellen',
                'category' => 'Lagepläne',
                'help' => 'Erlaubt das Erstellen, von Lageplänen'
            ],
            'EDIT_MAPS' => [
                'name' => 'Lagepläne bearbeiten',
                'category' => 'Lagepläne',
                'help' => 'Erlaubt das Bearbeiten von Lageplänen'
            ],
            'VIEW_MAPS' => [
                'name' => 'Lagepläne ansehen',
                'category' => 'Lagepläne',
                'help' => 'Erlaubt das Ansehen von Lageplänen'
            ],
            'DELETE_MAPS' => [
                'name' => 'Lagepläne löschen',
                'category' => 'Lagepläne',
                'help' => 'Erlaubt das Löschen von Lageplänen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MAPS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_MAPS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MAPS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MAPS');
    }
}
