<?php

namespace App\Skript;

use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Skript\Script
 *
 * @property ?string $Title
 * @property ?string $Hash
 * @property int $OrganizationID
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\ORM\DataList|\App\Skript\ScriptParagraph[] Paragraphs()
 * @method \SilverStripe\ORM\DataList|\App\Skript\ScriptRole[] Roles()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Script extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Hash"  => "Varchar(64)",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
    ];

    private static $has_many = [
        "Paragraphs" => ScriptParagraph::class,
        "Roles"      => ScriptRole::class,
    ];

    private static $field_labels = [
        "Title"        => "Titel",
        "Organization" => "Organisation",
        "Paragraphs"   => "Absätze",
        "Roles"        => "Rollen",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'Script';
    private static $singular_name = "Skript";
    private static $plural_name = "Skripte";

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->Hash) {
            $this->Hash = bin2hex(random_bytes(16));
        }
    }

    public function providePermissions()
    {
        return [
            'CREATE_SCRIPTS' => [
                'name'     => 'Skripte erstellen',
                'category' => 'Skripte',
                'help'     => 'Erlaubt das Erstellen von Skripten',
            ],
            'EDIT_SCRIPTS' => [
                'name'     => 'Skripte bearbeiten',
                'category' => 'Skripte',
                'help'     => 'Erlaubt das Bearbeiten von Skripten',
            ],
            'VIEW_SCRIPTS' => [
                'name'     => 'Skripte ansehen',
                'category' => 'Skripte',
                'help'     => 'Erlaubt das Ansehen von Skripten',
            ],
            'DELETE_SCRIPTS' => [
                'name'     => 'Skripte löschen',
                'category' => 'Skripte',
                'help'     => 'Erlaubt das Löschen von Skripten',
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_SCRIPTS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_SCRIPTS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_SCRIPTS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_SCRIPTS');
    }

    /**
     * Ob der Nutzer dieses Skript im Frontend überhaupt sehen darf: jedes aktive
     * Mitglied der zugehörigen Organisation. Unabhängig von den CMS-Permissions
     * oben, die für den SilverStripe-Admin-Bereich gelten.
     */
    public function isViewableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->isActiveMemberOfOrg($org);
    }

    /**
     * Ob der Nutzer dieses Skript (Absätze, Rollen, Rollen-Zuweisungen) bearbeiten darf.
     */
    public function isEditableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::SCRIPT_EDIT);
    }

    /**
     * Ob der Nutzer dieses Skript löschen darf.
     */
    public function isDeletableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::SCRIPT_EDIT);
    }
}
