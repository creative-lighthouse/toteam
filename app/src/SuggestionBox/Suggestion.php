<?php

namespace App\SuggestionBox;

use Override;
use App\Food\Food;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\SuggestionBox\Suggestion
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property bool $HasRecipient
 * @property bool $SeenByRecipient
 * @property bool $IsAnonymous
 * @property int $RecipientID
 * @property int $SenderID
 * @method \SilverStripe\Security\Member Recipient()
 * @method \SilverStripe\Security\Member Sender()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Suggestion extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "HasRecipient" => "Boolean",
        "SeenByRecipient" => "Boolean",
        "IsAnonymous" => "Boolean",
    ];

    private static $has_one = [
        "Recipient" => Member::class,
        "Sender" => Member::class,
    ];

    private static $owns = [
    ];

    private static $field_labels = [];

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
