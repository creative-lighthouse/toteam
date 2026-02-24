<?php

namespace App\Events;

use Override;
use App\Teams\Organization;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;

/**
 * Class \App\Events\Event
 *
 * @property ?string $Title
 * @property ?string $Start
 * @property ?string $End
 * @property int $ParentID
 * @property int $ImageID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\Assets\Image Image()
 * @method \SilverStripe\ORM\DataList|\App\Events\EventDay[] EventDays()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Event extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Start" => "Datetime",
        "End" => "Datetime",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
        "Image" => Image::class,
    ];

    private static $has_many = [
        "EventDays" => EventDay::class,
    ];

    private static $owns = [
        'Image',
        'EventDays',
    ];

    private static $field_labels = [
        "EventDays" => "Veranstaltungs-Tage",
        "Title" => "Titel",
        "Start" => "Start",
        "End" => "Ende",
        "Parent" => "Organisation",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "RenderStartDate" => "Start",
        "RenderEndDate" => "End"
    ];

    private static $table_name = 'Event';
    private static $singular_name = "Veranstaltung";
    private static $plural_name = "Veranstaltung";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Remove EventDays field initially
        $fields->removeByName('EventDays');
        
        // Only show EventDays GridFields if the Event has been saved
        if ($this->isInDB()) {
            $eventDaysGrid = GridField::create(
                'EventDaysGrid',
                'Veranstaltungstage',
                $this->EventDays()->filter('Date:GreaterThanOrEqual', date('Y-m-d')),
                GridFieldConfig_RecordEditor::create()
            );
            $oldEventDaysGrid = GridField::create(
                'OldEventDaysGrid',
                'Vergangene Veranstaltungstage',
                $this->EventDays()->filter('Date:LessThan', date('Y-m-d')),
                GridFieldConfig_RecordEditor::create()
            );
            $fields->addFieldToTab('Root.Main', $eventDaysGrid);
            $fields->addFieldToTab('Root.Archiv', $oldEventDaysGrid);
        } else {
            // Show a message that the Event needs to be saved first
            $fields->addFieldToTab('Root.Main', 
                \SilverStripe\Forms\LiteralField::create(
                    'EventDaysNotice',
                    '<p class="message notice">Bitte speichern Sie die Veranstaltung zunächst, bevor Sie Veranstaltungstage hinzufügen können.</p>'
                )
            );
        }
        
        return $fields;
    }

    public function RenderStartDate()
    {
        return $this->dbObject('Start')->Format('dd.MM.YYYY H:mm');
    }

    public function RenderEndDate()
    {
        return $this->dbObject('End')->Format('dd.MM.YYYY H:mm');
    }

    public function providePermissions()
    {
        return [
            'CREATE_EVENTS' => [
                'name' => 'Veranstaltungen erstellen',
                'category' => 'Events',
                'help' => 'Erlaubt das Erstellen, von Veranstaltungen'
            ],
            'EDIT_EVENTS' => [
                'name' => 'Veranstaltungen bearbeiten',
                'category' => 'Events',
                'help' => 'Erlaubt das Bearbeiten von Veranstaltungen'
            ],
            'VIEW_EVENTS' => [
                'name' => 'Veranstaltungen ansehen',
                'category' => 'Events',
                'help' => 'Erlaubt das Ansehen von Veranstaltungen'
            ],
            'DELETE_EVENTS' => [
                'name' => 'Veranstaltungen löschen',
                'category' => 'Events',
                'help' => 'Erlaubt das Löschen von Veranstaltungen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        //Check user for CREATE_EVENTS permission
        return Permission::check('CREATE_EVENTS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        //Check user for EDIT_EVENTS permission
        return Permission::check('EDIT_EVENTS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        //Check user for DELETE_EVENTS permission
        return Permission::check('DELETE_EVENTS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        //Check user for VIEW_EVENTS permission
        return Permission::check('VIEW_EVENTS', 'any', $member);
    }
}
