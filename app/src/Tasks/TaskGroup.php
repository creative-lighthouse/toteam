<?php

namespace App\Tasks;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Tasks\TaskGroup
 *
 * @method \SilverStripe\ORM\ManyManyList|\App\Tasks\Task[] Tasks()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class TaskGroup extends DataObject implements PermissionProvider
{
    private static $db = [
    ];

    private static $many_many = [
        'Tasks' => Task::class,
    ];

    private static $owns = [
    ];

    private static $field_labels = [
    ];

    private static $summary_fields = [
    ];

    private static $table_name = 'TaskGroup';
    private static $singular_name = "Aufgaben-Gruppe";
    private static $plural_name = "Aufgaben-Gruppe";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_TASKGROUPS' => [
                'name' => 'Aufgaben-Gruppen erstellen',
                'category' => 'Aufgaben',
                'help' => 'Erlaubt das Erstellen, von Aufgaben-Gruppen'
            ],
            'EDIT_TASKGROUPS' => [
                'name' => 'Aufgaben-Gruppen bearbeiten',
                'category' => 'Aufgaben',
                'help' => 'Erlaubt das Bearbeiten von Aufgaben-Gruppen'
            ],
            'VIEW_TASKGROUPS' => [
                'name' => 'Aufgaben-Gruppen ansehen',
                'category' => 'Aufgaben',
                'help' => 'Erlaubt das Ansehen von Aufgaben-Gruppen'
            ],
            'DELETE_TASKGROUPS' => [
                'name' => 'Aufgaben-Gruppen löschen',
                'category' => 'Aufgaben',
                'help' => 'Erlaubt das Löschen von Aufgaben-Gruppen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_TASKGROUPS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_TASKGROUPS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_TASKGROUPS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_TASKGROUPS');
    }
}
