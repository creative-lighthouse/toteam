<?php

namespace App\Events;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldEditButton;

/**
 * Class \App\Events\Event
 *
 * @property ?string $Title
 * @property ?string $Start
 * @property ?string $End
 * @property int $ImageID
 * @method \SilverStripe\Assets\Image Image()
 * @method \SilverStripe\ORM\DataList|\App\Events\EventDay[] EventDays()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Event extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Start" => "Datetime",
        "End" => "Datetime",
    ];

    private static $has_one = [
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

        //Move EventDays to Main Tab
        $eventDaysGrid = $fields->dataFieldByName('EventDays');
        $fields->removeByName('EventDays');
        $eventDaysGrid = GridField::create(
            'EventDaysGrid',
            'Veranstaltungstage',
            $this->EventDays(),
            GridFieldConfig_RecordEditor::create()
        );
        $fields->addFieldToTab('Root.Main', $eventDaysGrid);
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
}
