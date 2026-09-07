<?php

namespace App\Skript;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Skript\ScriptRole
 *
 * Eine "Rolle" (Figur) innerhalb eines Skripts. Lebt ausschließlich im Kontext
 * ihres Skripts (nicht organisationsweit wiederverwendbar, im Unterschied zu
 * App\Teams\OrgRole, das ein Berechtigungs-Bundle ist).
 *
 * @property ?string $Title
 * @property int $SortOrder
 * @property int $ScriptID
 * @method \App\Skript\Script Script()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Members()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class ScriptRole extends DataObject
{
    private static $db = [
        "Title"     => "Varchar(255)",
        "SortOrder" => "Int",
    ];

    private static $has_one = [
        "Script" => Script::class,
    ];

    private static $many_many = [
        "Members" => Member::class,
    ];

    private static $default_sort = "SortOrder ASC";

    private static $field_labels = [
        "Title"   => "Titel",
        "Script"  => "Skript",
        "Members" => "Zugewiesene Mitglieder",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'ScriptRole';
    private static $singular_name = "Skript-Rolle";
    private static $plural_name = "Skript-Rollen";

    public function canCreate($member = null, $context = [])
    {
        return $this->Script()->canEdit($member);
    }

    public function canEdit($member = null, $context = [])
    {
        return $this->Script()->canEdit($member);
    }

    public function canView($member = null, $context = [])
    {
        return $this->Script()->canView($member);
    }

    public function canDelete($member = null, $context = [])
    {
        return $this->Script()->canEdit($member);
    }
}
