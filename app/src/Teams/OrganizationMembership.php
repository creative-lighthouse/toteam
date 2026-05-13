<?php

namespace App\Teams;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Teams\OrganizationMembership
 *
 * @property ?string $Role
 * @property ?string $JoinedDate
 * @property int $MemberID
 * @property int $OrganizationID
 * @method \SilverStripe\Security\Member Member()
 * @method \App\Teams\Organization Organization()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class OrganizationMembership extends DataObject implements PermissionProvider
{
    private static $table_name = 'OrganizationMembership';
    private static $singular_name = 'Organisationsmitgliedschaft';
    private static $plural_name = 'Organisationsmitgliedschaften';

    private static $db = [
        'Role'       => "Enum('applicant,member,moderator,admin','applicant')",
        'JoinedDate' => 'Date',
    ];

    private static $has_one = [
        'Member'       => Member::class,
        'Organization' => Organization::class,
    ];

    private static $field_labels = [
        'Role'               => 'Rolle',
        'JoinedDate'         => 'Beitrittsdatum',
        'Member.Name'        => 'Mitglied',
        'Organization.Title' => 'Organisation',
    ];

    private static $summary_fields = [
        'Member.Name'        => 'Mitglied',
        'Organization.Title' => 'Organisation',
        'Role'               => 'Rolle',
        'JoinedDate'         => 'Beitrittsdatum',
    ];

    public function isAdmin(): bool
    {
        return $this->Role === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->Role === 'moderator';
    }

    public function isActiveMember(): bool
    {
        return in_array($this->Role, ['member', 'moderator', 'admin']);
    }

    public function canManageContent(): bool
    {
        return in_array($this->Role, ['moderator', 'admin']);
    }

    public function isApplicant(): bool
    {
        return $this->Role === 'applicant';
    }

    public function approve(string $role = 'member'): void
    {
        $this->Role = $role;
        if (!$this->JoinedDate) {
            $this->JoinedDate = date('Y-m-d');
        }
        $this->write();
    }

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        // Auto-set JoinedDate the first time an active role is assigned
        if (!$this->JoinedDate && $this->isActiveMember()) {
            $this->JoinedDate = date('Y-m-d');
        }
    }

    public function providePermissions(): array
    {
        return [
            'MANAGE_ORGANIZATION_MEMBERSHIPS' => [
                'name'     => 'Organisationsmitgliedschaften verwalten',
                'category' => 'Teams',
                'help'     => 'Erlaubt das Verwalten von Mitgliedschaften in Organisationen',
            ],
        ];
    }

    public function canView($member = null, $context = []): bool
    {
        return Permission::checkMember($member, 'VIEW_ORGANIZATIONS');
    }

    public function canCreate($member = null, $context = []): bool
    {
        return Permission::checkMember($member, 'MANAGE_ORGANIZATION_MEMBERSHIPS');
    }

    public function canEdit($member = null, $context = []): bool
    {
        return Permission::checkMember($member, 'MANAGE_ORGANIZATION_MEMBERSHIPS');
    }

    public function canDelete($member = null, $context = []): bool
    {
        return Permission::checkMember($member, 'MANAGE_ORGANIZATION_MEMBERSHIPS');
    }
}
