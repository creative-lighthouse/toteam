<?php

namespace App\Marketing;

use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Marketing\PosterDistribution
 *
 * Ein Eintrag "wo wurden wie viele Plakate welcher Größe verteilt", optional
 * mit GPS-Koordinaten. `Created` (von SilverStripe automatisch gepflegt) dient
 * als Zeitstempel für die jahresweise Rückschau im Frontend.
 *
 * @property ?string $Location
 * @property int $Quantity
 * @property ?string $Latitude
 * @property ?string $Longitude
 * @property ?string $Note
 * @property int $PosterSizeID
 * @property int $OrganizationID
 * @property int $MemberID
 * @method \App\Marketing\PosterSize PosterSize()
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class PosterDistribution extends DataObject
{
    private static $db = [
        "Location"  => "Varchar(255)",
        "Quantity"  => "Int",
        "Latitude"  => "Varchar(30)",
        "Longitude" => "Varchar(30)",
        "Note"      => "Text",
    ];

    private static $has_one = [
        "PosterSize"   => PosterSize::class,
        "Organization" => Organization::class,
        "Member"       => Member::class,
    ];

    private static $default_sort = "Created DESC";

    private static $field_labels = [
        "Location"     => "Ort",
        "Quantity"     => "Anzahl",
        "Latitude"     => "Breitengrad",
        "Longitude"    => "Längengrad",
        "Note"         => "Notiz",
        "PosterSize"   => "Größe",
        "Organization" => "Organisation",
        "Member"       => "Erfasst von",
    ];

    private static $summary_fields = [
        "Location"        => "Ort",
        "Quantity"        => "Anzahl",
        "PosterSize.Title" => "Größe",
        "Member.Name"     => "Erfasst von",
        "Created"         => "Datum",
    ];

    private static $table_name = 'PosterDistribution';
    private static $singular_name = "Plakat-Verteilung";
    private static $plural_name = "Plakat-Verteilungen";

    public function isViewableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->isActiveMemberOfOrg($org);
    }

    /**
     * Ein Eintrag darf von seinem Autor oder von jemandem mit
     * MARKETING_MANAGE_ENTRIES bearbeitet/gelöscht werden.
     */
    public function isEditableBy(Member $member): bool
    {
        $org = $this->Organization();
        if (!$org || !$org->exists()) {
            return false;
        }
        if ((int) $this->MemberID === (int) $member->ID) {
            return true;
        }
        return $member->hasOrgPermission($org, OrgPermissions::MARKETING_MANAGE_ENTRIES);
    }

    public function isDeletableBy(Member $member): bool
    {
        return $this->isEditableBy($member);
    }
}
