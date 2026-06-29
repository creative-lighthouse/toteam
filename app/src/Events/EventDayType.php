<?php

namespace App\Events;

use Override;
use App\Events\EventDay;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Events\EventDayType
 *
 * @property ?string $Title
 * @property ?string $PluralTitle
 * @property int $IconID
 * @method \SilverStripe\Assets\Image Icon()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class EventDayType extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "PluralTitle" => "Varchar(255)",
    ];

    private static $has_one = [
        "Icon" => Image::class,
    ];

    private static $owns = [
        'Icon',
    ];

    private static $belongs_many = [
        'EventDays' => EventDay::class,
    ];

    private static $field_labels = [];

    private static $summary_fields = [
        "Title",
        "PluralTitle",
    ];

    private static $table_name = 'EventDayType';
    private static $singular_name = "Tagestyp";
    private static $plural_name = "Tagestypen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function RenderStartDate()
    {
        return $this->dbObject('Start')->Format('d.m.Y H:i');
    }

    public function RenderEndDate()
    {
        return $this->dbObject('End')->Format('d.m.Y H:i');
    }

    public function providePermissions()
    {
        return [
            'CREATE_EVENTDAYTYPES' => [
                'name' => 'Tagestypen erstellen',
                'category' => 'Events',
                'help' => 'Erlaubt das Erstellen, von Tagestypen'
            ],
            'EDIT_EVENTDAYTYPES' => [
                'name' => 'Tagestypen bearbeiten',
                'category' => 'Events',
                'help' => 'Erlaubt das Bearbeiten von Tagestypen'
            ],
            'VIEW_EVENTDAYTYPES' => [
                'name' => 'Tagestypen ansehen',
                'category' => 'Events',
                'help' => 'Erlaubt das Ansehen von Tagestypen'
            ],
            'DELETE_EVENTDAYTYPES' => [
                'name' => 'Tagestypen löschen',
                'category' => 'Events',
                'help' => 'Erlaubt das Löschen von Tagestypen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        //Check user for CREATE_EVENTDAYTYPES permission
        return Permission::check('CREATE_EVENTDAYTYPES', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        //Check user for EDIT_EVENTDAYTYPES permission
        return Permission::check('EDIT_EVENTDAYTYPES', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        //Check user for DELETE_EVENTDAYTYPES permission
        return Permission::check('DELETE_EVENTDAYTYPES', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        //Check user for VIEW_EVENTDAYTYPES permission
        return Permission::check('VIEW_EVENTDAYTYPES', 'any', $member);
    }
}
