<?php

namespace App\Food;

use Override;
use App\Food\Meal;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Food\MealEater
 *
 * @property ?string $Type
 * @property int $ParentID
 * @property int $MemberID
 * @method \App\Food\Meal Parent()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MealEater extends DataObject implements PermissionProvider
{
    private static $db = [
        "Type" => "Enum('Accept, Decline')",
    ];

    private static $has_one = [
        'Parent' => Meal::class,
        "Member" => Member::class
    ];

    private static $field_labels = [
        "Member" => "Benutzer",
        "Type" => "Nimmt teil",
    ];

    private static $summary_fields = [
        "Member.Title" => "Benutzer",
        "Type" => "Nimmt teil",
    ];

    private static $table_name = 'MealEater';
    private static $singular_name = "Mahlzeit-Teilnehmer";
    private static $plural_name = "Mahlzeit-Teilnehmer";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("ParentID");
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_MEALEATERS' => [
                'name' => 'Mahlzeit-Teilnehmer erstellen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Erstellen, von Mahlzeit-Teilnehmern'
            ],
            'EDIT_MEALEATERS' => [
                'name' => 'Mahlzeit-Teilnehmer bearbeiten',
                'category' => 'Essen',
                'help' => 'Erlaubt das Bearbeiten von Mahlzeit-Teilnehmern'
            ],
            'VIEW_MEALEATERS' => [
                'name' => 'Mahlzeit-Teilnehmer ansehen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Ansehen von Mahlzeit-Teilnehmern'
            ],
            'DELETE_MEALEATERS' => [
                'name' => 'Mahlzeit-Teilnehmer löschen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Löschen von Mahlzeit-Teilnehmern'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        //Check user for CREATE_MEALEATERS permission
        return Permission::check('CREATE_MEALEATERS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        //Check user for EDIT_MEALEATERS permission
        return Permission::check('EDIT_MEALEATERS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        //Check user for DELETE_MEALEATERS permission
        return Permission::check('DELETE_MEALEATERS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        //Check user for VIEW_MEALEATERS permission
        return Permission::check('VIEW_MEALEATERS', 'any', $member);
    }
}
