<?php

namespace App\Tasks;

use App\Tasks\TaskGroup;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Tasks\Task
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $Deadline
 * @property ?string $State
 * @property ?string $Hash
 * @property int $ParentID
 * @property int $OrganizationID
 * @property int $OwnerID
 * @method \App\Tasks\Task Parent()
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\Security\Member Owner()
 * @method \SilverStripe\ORM\ManyManyList|\App\Tasks\TaskGroup[] TaskGroups()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Supporters()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Task extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title"       => "Varchar(255)",
        "Description" => "Text",
        "Deadline"    => "Datetime",
        "State"       => "Enum('open,in_progress,feedback,finished', 'open')",
        "Hash"        => "Varchar(64)",
    ];

    private static $has_one = [
        "Parent"       => Task::class,
        "Organization" => Organization::class,
        "Owner"        => Member::class,
    ];

    private static $many_many = [
        'TaskGroups' => TaskGroup::class,
        'Supporters' => Member::class,
    ];

    private static $field_labels = [
        "Title"        => "Titel",
        "Description"  => "Beschreibung",
        "Deadline"     => "Fälligkeitsdatum",
        "State"        => "Status",
        "Owner"        => "Verantwortlicher",
        "Organization" => "Organisation",
        "TaskGroups"   => "Aufgaben-Gruppen",
        "Supporters"   => "Unterstützer",
        "Parent"       => "Übergeordnete Aufgabe",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'Task';
    private static $singular_name = "Aufgabe";
    private static $plural_name = "Aufgaben";

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->Hash) {
            $this->Hash = bin2hex(random_bytes(16));
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_TASKS' => [
                'name'     => 'Aufgaben erstellen',
                'category' => 'Aufgaben',
                'help'     => 'Erlaubt das Erstellen, von Aufgaben'
            ],
            'EDIT_TASKS' => [
                'name'     => 'Aufgaben bearbeiten',
                'category' => 'Aufgaben',
                'help'     => 'Erlaubt das Bearbeiten von Aufgaben'
            ],
            'VIEW_TASKS' => [
                'name'     => 'Aufgaben ansehen',
                'category' => 'Aufgaben',
                'help'     => 'Erlaubt das Ansehen von Aufgaben'
            ],
            'DELETE_TASKS' => [
                'name'     => 'Aufgaben löschen',
                'category' => 'Aufgaben',
                'help'     => 'Erlaubt das Löschen von Aufgaben'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_TASKS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_TASKS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_TASKS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_TASKS');
    }

    /**
     * Ob der Nutzer diese Aufgabe im Frontend überhaupt sehen darf: jedes aktive
     * Mitglied der zugehörigen Organisation. Unabhängig von den CMS-Permissions
     * oben, die für den SilverStripe-Admin-Bereich gelten.
     */
    public function isViewableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->isActiveMemberOfOrg($org);
    }

    /**
     * Ob der Nutzer diese Aufgabe (Status/Verantwortlicher/Unterstützer) bearbeiten darf.
     */
    public function isEditableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::TASKS_EDIT);
    }

    /**
     * Ob der Nutzer diese Aufgabe löschen darf.
     */
    public function isDeletableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::TASKS_DELETE);
    }
}
