<?php

namespace App\SuggestionBox;

use Override;
use App\Food\Food;
use App\Teams\Organization;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\SuggestionBox\Suggestion
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property bool $SeenByRecipient
 * @property bool $IsAnonymous
 * @property int $ParentID
 * @property int $RecipientID
 * @property int $SenderID
 * @method \App\Teams\Organization Parent()
 * @method \SilverStripe\Security\Member Recipient()
 * @method \SilverStripe\Security\Member Sender()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Suggestion extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "SeenByRecipient" => "Boolean",
        "IsAnonymous" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => Organization::class,
        "Recipient" => Member::class,
        "Sender" => Member::class,
    ];

    private static $owns = [
    ];

    private static $field_labels = [
        "Parent" => "Organisation",
        "Recipient" => "Empfänger",
        "Sender" => "Absender",
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "SeenByRecipient" => "Vom Empfänger gesehen",
        "IsAnonymous" => "Ist anonym",
    ];

    private static $summary_fields = [
        "Title"
    ];

    private static $table_name = 'Suggestion';
    private static $singular_name = "Kritik/Vorschlag";
    private static $plural_name = "Kritik/Vorschläge";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function providePermissions()
    {
        return [
            'CREATE_SUGGESTIONS' => [
                'name' => 'Kummerkasten-Eintrag erstellen',
                'category' => 'Kummerkasten',
                'help' => 'Erlaubt das Erstellen, von Einträgen im Kummerkasten'
            ],
            'EDIT_SUGGESTIONS' => [
                'name' => 'Kummerkasten-Einträge bearbeiten',
                'category' => 'Kummerkasten',
                'help' => 'Erlaubt das Bearbeiten von Einträgen im Kummerkasten'
            ],
            'VIEW_SUGGESTIONS' => [
                'name' => 'Kummerkasten-Einträge ansehen',
                'category' => 'Kummerkasten',
                'help' => 'Erlaubt das Ansehen von Einträgen im Kummerkasten'
            ],
            'DELETE_SUGGESTIONS' => [
                'name' => 'Kummerkasten-Einträge löschen',
                'category' => 'Kummerkasten',
                'help' => 'Erlaubt das Löschen von Einträgen im Kummerkasten'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_SUGGESTIONS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_SUGGESTIONS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_SUGGESTIONS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_SUGGESTIONS');
    }
}
