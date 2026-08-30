<?php

namespace App\Rooms;

use App\Tasks\Task;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Rooms\Room
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property int $OrganizationID
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\ORM\ManyManyList|\App\Tasks\Task[] Tasks()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Room extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title"       => "Varchar(255)",
        "Description" => "Text",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
    ];

    private static $many_many = [
        'Tasks' => Task::class,
    ];

    private static $field_labels = [
        "Title"        => "Titel",
        "Description"  => "Beschreibung",
        "Organization" => "Organisation",
        "Tasks"        => "Aufgaben",
    ];

    private static $summary_fields = [
        "Title"              => "Titel",
        "Organization.Title" => "Organisation",
    ];

    private static $table_name = 'Room';
    private static $singular_name = "Raum";
    private static $plural_name = "Räume";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $tasksField = $fields->dataFieldByName('Tasks');
        if ($tasksField) {
            $tasksField->setConfig(GridFieldConfig_RelationEditor::create());
            $fields->removeByName('Tasks');
            $fields->addFieldToTab('Root.Main', $tasksField);
        }

        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_ROOMS' => [
                'name'     => 'Räume erstellen',
                'category' => 'Räume',
                'help'     => 'Erlaubt das Erstellen von Räumen'
            ],
            'EDIT_ROOMS' => [
                'name'     => 'Räume bearbeiten',
                'category' => 'Räume',
                'help'     => 'Erlaubt das Bearbeiten von Räumen'
            ],
            'VIEW_ROOMS' => [
                'name'     => 'Räume ansehen',
                'category' => 'Räume',
                'help'     => 'Erlaubt das Ansehen von Räumen'
            ],
            'DELETE_ROOMS' => [
                'name'     => 'Räume löschen',
                'category' => 'Räume',
                'help'     => 'Erlaubt das Löschen von Räumen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_ROOMS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_ROOMS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_ROOMS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_ROOMS');
    }

    /**
     * Ob der Nutzer diesen Raum im Frontend überhaupt sehen darf: jedes aktive
     * Mitglied der zugehörigen Organisation. Unabhängig von den CMS-Permissions
     * oben, die für den SilverStripe-Admin-Bereich gelten.
     */
    public function isViewableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->isActiveMemberOfOrg($org);
    }

    public function isEditableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::ROOMS_EDIT);
    }

    public function isDeletableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::ROOMS_DELETE);
    }
}
