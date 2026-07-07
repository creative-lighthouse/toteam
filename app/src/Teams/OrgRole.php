<?php

namespace App\Teams;

use Override;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\Teams\OrgRole
 *
 * Eine von einer Organisation selbst definierte Rolle (z.B. "Kassenwart"),
 * der granulare Berechtigungen aus App\Teams\OrgPermissions zugewiesen werden.
 * Mitgliedschaften (OrganizationMembership) können mehrere Rollen gleichzeitig haben.
 *
 * @property ?string $Title
 * @property ?string $Permissions
 * @property int $SortOrder
 * @property int $OrganizationID
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\OrganizationMembership[] Memberships()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class OrgRole extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Permissions" => "Text",
        "SortOrder" => "Int",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
    ];

    private static $belongs_many_many = [
        "Memberships" => OrganizationMembership::class . '.Roles',
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Organization" => "Organisation",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Organization.Title" => "Organisation",
    ];

    private static $default_sort = 'SortOrder ASC';

    private static $table_name = 'OrgRole';
    private static $singular_name = "Rolle";
    private static $plural_name = "Rollen";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('SortOrder');
        $fields->removeByName('Permissions');
        return $fields;
    }

    /**
     * @return string[]
     */
    public function getPermissionCodes(): array
    {
        if (!$this->Permissions) {
            return [];
        }
        $decoded = json_decode($this->Permissions, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param string[] $codes
     */
    public function setPermissionCodes(array $codes): void
    {
        $valid = array_intersect($codes, OrgPermissions::allCodes());
        $this->Permissions = json_encode(array_values(array_unique($valid)));
    }

    public function hasPermission(string $code): bool
    {
        $codes = $this->getPermissionCodes();
        return in_array(OrgPermissions::ORG_ADMIN, $codes, true) || in_array($code, $codes, true);
    }
}
