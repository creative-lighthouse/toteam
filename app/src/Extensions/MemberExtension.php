<?php

namespace App\Extensions;

use App\Tasks\Task;
use App\Teams\Department;
use SilverStripe\Assets\Image;
use App\HumanResources\Allergy;
use SilverStripe\Core\Extension;
use App\SuggestionBox\Suggestion;
use SilverStripe\Forms\FieldList;
use App\Calendar\Absence;
use App\Calendar\AppointmentParticipation;
use App\Events\EventDayParticipation;
use SilverStripe\Forms\DropdownField;
use App\Controllers\AnnouncementsController;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;

/**
 * Class \App\Extensions\MemberExtension
 *
 * @property \SilverStripe\Security\Member|\App\Extensions\MemberExtension $owner
 * @property ?string $Joindate
 * @property ?string $Username
 * @property ?string $NameVisibility
 * @property ?string $FoodPreference
 * @property ?string $DateOfBirth
 * @property ?string $Hash
 * @property bool $NotifyEvents
 * @property bool $NotifyAnnouncements
 * @property bool $NotifyMeals
 * @property bool $NotifyMaps
 * @property bool $NotifyApplications
 * @property int $ProfileImageID
 * @method \SilverStripe\Assets\Image ProfileImage()
 * @method \SilverStripe\ORM\DataList|\App\Teams\OrganizationMembership[] OrganizationMemberships()
 * @method \SilverStripe\ORM\ManyManyList|\App\HumanResources\Allergy[] Allergies()
 */
class MemberExtension extends Extension
{
    private static $db = [
        "Joindate"         => "Date",
        "Username"         => "Varchar(50)",
        "NameVisibility"   => "Enum('full,first,username','full')",
        "FoodPreference" => "Varchar(255)",
        "DateOfBirth"    => "Date",
        "Hash"           => "Varchar(255)",
        "NotifyEvents"   => "Boolean(1)",
        "NotifyAnnouncements" => "Boolean(1)",
        "NotifyMeals"    => "Boolean(1)",
        "NotifyMaps"         => "Boolean(1)",
        "NotifyApplications" => "Boolean(1)",
    ];

    private static $defaults = [
        "NotifyEvents"       => true,
        "NotifyAnnouncements" => true,
        "NotifyMeals"        => true,
        "NotifyMaps"         => true,
        "NotifyApplications" => true,
    ];

    private static $has_one = [
        "ProfileImage" => Image::class,
    ];

    private static $many_many = [
        "Allergies" => Allergy::class,
    ];

    private static $has_many = [
        "OrganizationMemberships" => OrganizationMembership::class . '.Member',
    ];

    private static $belongs_many = [
        "Tasks"       => Task::class,
        "Suggestions" => Suggestion::class,
    ];

    private static $owns = [
        'ProfileImage',
    ];

    private static $field_labels = [
        'Joindate' => 'Mitglied seit',
        'FoodPreference' => 'Essenspräferenz',
        'Allergies' => 'Allergien',
    ];

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->replaceField('FoodPreference', DropdownField::create('FoodPreference', 'Essenspräferenz', [
            'None' => 'Keine',
            'Vegetarian' => 'Vegetarisch',
            'Vegan' => 'Vegan',
        ]));
        return $fields;
    }

    /**
     * Event handler called before writing to the database.
     *
     * @uses DataExtension->onAfterWrite()
     */
    public function onBeforeWrite()
    {
        if (!$this->owner->Hash) {
            $this->owner->Hash = md5(uniqid(rand(), true));
        }
    }

    public function onBeforeDelete()
    {
        $id = $this->owner->ID;

        foreach (Absence::get()->filter('MemberID', $id) as $absence) {
            $absence->Organisations()->removeAll();
            $absence->delete();
        }

        foreach (AppointmentParticipation::get()->filter('MemberID', $id) as $participation) {
            $participation->delete();
        }

        foreach (EventDayParticipation::get()->filter('MemberID', $id) as $participation) {
            $participation->delete();
        }

        foreach (OrganizationMembership::get()->filter('MemberID', $id) as $membership) {
            $membership->delete();
        }
    }

    public function getParticipations()
    {
        return EventDayParticipation::get()->filter('MemberID', $this->owner->ID);
    }

    public function RenderFoodPreference()
    {
        switch ($this->owner->FoodPreference) {
            case 'Vegetarian':
                return 'Vegetarisch';
            case 'Vegan':
                return 'Vegan';
            default:
                return 'Keine';
        }
    }

    public function RenderAllergies()
    {
        $allergies = $this->owner->Allergies();
        if ($allergies->count() == 0) {
            return 'Keine Allergien';
        }

        $titles = [];
        foreach ($allergies as $allergy) {
            $titles[] = $allergy->Title;
        }
        return implode(', ', $titles);
    }

    public function getGravatar($size = 200)
    {
        //Generate a Gravatar for the user
        $s = $size; //Size in pixels (max 2048)
        $d = 'identicon'; //Default replacement for missing image
        $r = 'g'; //Rating
        $img = false; //Returning full image tag
        $atts = array(); //Extra attributes to add

        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($this->owner->Email ?? '')));
        $url .= "?s=$s&d=$d&r=$r";
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val) {
                $url .= ' ' . $key . '="' . $val . '"';
            }
            $url .= ' />';
        }
        return $url;
    }

    public function RenderName()
    {
        return $this->owner->FirstName;
    }

    public function getDisplayName(): string
    {
        $visibility = $this->owner->NameVisibility ?: 'full';
        return match ($visibility) {
            'first'    => $this->owner->FirstName ?? '',
            'username' => $this->owner->Username ?: ($this->owner->FirstName ?? ''),
            default    => trim(($this->owner->FirstName ?? '') . ' ' . ($this->owner->Surname ?? '')),
        };
    }

    public function RenderProfileImage()
    {
        if ($this->owner->ProfileImageID && $this->owner->ProfileImage()->exists()) {
            return $this->owner->ProfileImage()->ScaleWidth(200)->getURL();
        }
        return $this->getGravatar(200);
    }

    public function getTodaysParticipations()
    {
        $today = date('Y-m-d');
        return $this->getParticipations()->filter('Parent.Date', $today)->filterAny('Type', ['Accept', 'Maybe']);
    }

    public function getUnreadAnnouncements()
    {
        return AnnouncementsController::getUnreadAnnouncements($this->owner->ID);
    }

    public function getMembershipInOrg(Organization $org): ?OrganizationMembership
    {
        return OrganizationMembership::get()->filter([
            'MemberID'       => $this->owner->ID,
            'OrganizationID' => $org->ID,
        ])->first();
    }

    public function getRoleInOrg(Organization $org): ?string
    {
        return $this->getMembershipInOrg($org)?->Role;
    }

    /**
     * Ob der Nutzer in dieser Organisation eine Rolle mit der angegebenen granularen
     * Berechtigung (App\Teams\OrgPermissions) hat. ORG_ADMIN wirkt dabei als Wildcard.
     */
    public function hasOrgPermission(Organization $org, string $code): bool
    {
        $membership = $this->getMembershipInOrg($org);
        if (!$membership || $membership->Role !== 'member') {
            return false;
        }

        foreach ($membership->Roles() as $role) {
            if ($role->hasPermission($code)) {
                return true;
            }
        }
        return false;
    }

    public function isAdminOfOrg(Organization $org): bool
    {
        return $this->hasOrgPermission($org, OrgPermissions::ORG_ADMIN);
    }

    /**
     * Alle Berechtigungs-Codes, die der Nutzer in dieser Organisation effektiv hat
     * (Vereinigung aller zugewiesenen Rollen). Bei ORG_ADMIN werden alle Codes
     * zurückgegeben, da ORG_ADMIN als Wildcard wirkt. Fürs Frontend gedacht, damit
     * dort einfache `Permissions.includes('CODE')`-Prüfungen möglich sind.
     * @return string[]
     */
    public function getOrgPermissionCodes(Organization $org): array
    {
        $membership = $this->getMembershipInOrg($org);
        if (!$membership || $membership->Role !== 'member') {
            return [];
        }

        $codes = [];
        foreach ($membership->Roles() as $role) {
            if ($role->hasPermission(OrgPermissions::ORG_ADMIN)) {
                return OrgPermissions::allCodes();
            }
            $codes = [...$codes, ...$role->getPermissionCodes()];
        }
        return array_values(array_unique($codes));
    }

    public function isActiveMemberOfOrg(Organization $org): bool
    {
        return $this->getMembershipInOrg($org)?->Role === 'member';
    }

    public function getOrganizationIDs(): array
    {
        return OrganizationMembership::get()
            ->filter([
                'MemberID' => $this->owner->ID,
                'Role'     => 'member',
            ])
            ->column('OrganizationID');
    }
}
