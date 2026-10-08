<?php

namespace App\Maps;

use App\Teams\OrgEvent;
use App\Teams\OrgEventItemPlacement;
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
 * @property int $EventID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\Security\Member Author()
 * @method \SilverStripe\Assets\Image BackgroundImage()
 * @method \App\Teams\OrgEvent Event()
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
        // Gesetzt bei Lageplänen eines Events (OrgEvent.SitePlans): sie erscheinen nicht
        // in der allgemeinen Übersicht, und wer das Event verwalten darf, darf sie bearbeiten
        "Event" => OrgEvent::class,
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
        "Event" => "Event",
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

        // Lagepläne eines Events sind interne Planung — kein Hinweis an alle Mitglieder
        if ($isNew && !$this->EventID) {
            PushNotificationService::notifyNewMap($this);
        }
    }

    /**
     * Objekte eines Events, die auf diesem Plan standen, gelten danach als nicht
     * platziert — ihre Notizen (z.B. DMX-Adressen) bleiben erhalten
     */
    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        foreach (OrgEventItemPlacement::get()->filter('MapID', $this->ID) as $placement) {
            $placement->MapID = 0;
            $placement->Coordinates = null;
            if (trim((string) $placement->Note) === '' && trim((string) $placement->MarkerText) === '') {
                $placement->delete();
            } else {
                $placement->write();
            }
        }
    }

    /**
     * Kopiert den Plan samt Ebenen und Markern als eigenen Lageplan eines Events.
     * Hintergrund- und Ebenenbilder werden nicht dupliziert, sondern mitbenutzt
     * (Uploads ersetzen nur die Verknüpfung, sie löschen keine Dateien).
     */
    public function copyForEvent(OrgEvent $event, string $title, int $authorID): Map
    {
        $copy = Map::create([
            'Title'                 => $title,
            'ShortText'             => $this->ShortText,
            'CoordinatesUpperLeft'  => $this->CoordinatesUpperLeft,
            'CoordinatesUpperRight' => $this->CoordinatesUpperRight,
            'CoordinatesLowerLeft'  => $this->CoordinatesLowerLeft,
            'CoordinatesLowerRight' => $this->CoordinatesLowerRight,
            'Active'                => true,
            'ParentID'              => $event->OrganizationID,
            'EventID'               => $event->ID,
            'AuthorID'              => $authorID,
            'BackgroundImageID'     => $this->BackgroundImageID,
        ]);
        $copy->write();

        foreach ($this->MapLayers() as $layer) {
            $layerCopy = MapLayer::create([
                'Title'       => $layer->Title,
                'Description' => $layer->Description,
                'Active'      => $layer->Active,
                'LayerColor'  => $layer->LayerColor,
                'SortOrder'   => $layer->SortOrder,
                'ImageID'     => $layer->ImageID,
                'ParentID'    => $copy->ID,
            ]);
            $layerCopy->write();
            // Räume gehören zur Organisation — beim Kopieren in eine andere werden
            // Raummarker zu normalen Markern
            $keepRooms = (int) $this->ParentID === (int) $event->OrganizationID;
            foreach ($layer->POIs() as $poi) {
                $isRoom = $poi->Type === 'room' && $keepRooms;
                MapPOI::create([
                    'Title'       => $poi->Title,
                    'MarkerText'  => $poi->getField('MarkerText'),
                    'Description' => $poi->Description,
                    'Active'      => $poi->Active,
                    'Coordinates' => $poi->Coordinates,
                    'Type'        => $isRoom ? 'room' : 'marker',
                    'RoomID'      => $isRoom ? $poi->RoomID : 0,
                    'ParentID'    => $layerCopy->ID,
                ])->write();
            }
        }
        return $copy;
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
