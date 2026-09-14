<?php

namespace App\Marketing;

use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Marketing\PosterSize
 *
 * Eine von Admins gepflegte Plakat-Größe/-Art (z.B. "A3", "Flyer DIN Lang"),
 * aus der Mitglieder beim Erfassen einer Verteilung auswählen. Organisationsweit
 * gültig, nicht an ein einzelnes Verteil-Jahr gebunden.
 *
 * @property ?string $Title
 * @property int $SortOrder
 * @property int $OrganizationID
 * @method \App\Teams\Organization Organization()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class PosterSize extends DataObject
{
    private static $db = [
        "Title"     => "Varchar(255)",
        "SortOrder" => "Int",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
    ];

    private static $default_sort = "SortOrder ASC";

    private static $field_labels = [
        "Title"        => "Titel",
        "Organization" => "Organisation",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'PosterSize';
    private static $singular_name = "Plakat-Größe";
    private static $plural_name = "Plakat-Größen";

    public function isViewableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->isActiveMemberOfOrg($org);
    }

    public function isEditableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::MARKETING_MANAGE_SIZES);
    }

    public function isDeletableBy(Member $member): bool
    {
        return $this->isEditableBy($member);
    }
}
