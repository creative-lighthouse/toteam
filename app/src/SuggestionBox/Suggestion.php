<?php

namespace App\SuggestionBox;

use Override;
use App\Food\Food;
use App\Teams\Organization;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

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
class Suggestion extends DataObject
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
}
