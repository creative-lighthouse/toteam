<?php

namespace App\Calendar;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Calendar\AppointmentType
 *
 * @property ?string $Title
 * @property ?string $PluralTitle
 * @property int $IconID
 * @method \SilverStripe\Assets\Image Icon()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class AppointmentType extends DataObject implements PermissionProvider
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

    private static $field_labels = [
        "Title" => "Titel",
        "PluralTitle" => "Titel (Plural)",
    ];

    private static $summary_fields = [
        "Title",
        "PluralTitle",
    ];

    private static $table_name = 'AppointmentType';
    private static $singular_name = "Termintyp";
    private static $plural_name = "Termintypen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_APPOINTMENTTYPES' => [
                'name' => 'Termintypen erstellen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Erstellen von Termintypen'
            ],
            'EDIT_APPOINTMENTTYPES' => [
                'name' => 'Termintypen bearbeiten',
                'category' => 'Termine',
                'help' => 'Erlaubt das Bearbeiten von Termintypen'
            ],
            'VIEW_APPOINTMENTTYPES' => [
                'name' => 'Termintypen ansehen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Ansehen von Termintypen'
            ],
            'DELETE_APPOINTMENTTYPES' => [
                'name' => 'Termintypen löschen',
                'category' => 'Termine',
                'help' => 'Erlaubt das Löschen von Termintypen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CREATE_APPOINTMENTTYPES', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::check('EDIT_APPOINTMENTTYPES', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::check('DELETE_APPOINTMENTTYPES', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        return Permission::check('VIEW_APPOINTMENTTYPES', 'any', $member);
    }
}
