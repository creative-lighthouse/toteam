<?php

namespace App\Skript;

use SilverStripe\ORM\DataObject;

/**
 * Class \App\Skript\ScriptParagraph
 *
 * Ein einzelner nummerierter Absatz eines Skripts. Trägt Rich-Text-Inhalt
 * (auf ein einfaches Tag-Allowlist beschränkt, siehe sanitizeContent()) und
 * kann einer oder mehreren ScriptRole(n) zugeordnet werden.
 *
 * @property ?string $Content
 * @property int $SortOrder
 * @property int $ScriptID
 * @method \App\Skript\Script Script()
 * @method \SilverStripe\ORM\ManyManyList|\App\Skript\ScriptRole[] Roles()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class ScriptParagraph extends DataObject
{
    /**
     * Erlaubte Tags für den Rich-Text-Inhalt: einfache Textauszeichnung, Listen
     * und Überschriften H2-H4 (zur Gliederung, siehe Überschriften-Sidebar im Frontend).
     */
    private const ALLOWED_TAGS = '<b><strong><i><em><u><ul><ol><li><p><br><h2><h3><h4>';

    private static $db = [
        "Content"      => "HTMLText",
        "SortOrder"    => "Int",
        "IsDirection"  => "Boolean(0)",
    ];

    private static $has_one = [
        "Script" => Script::class,
    ];

    private static $many_many = [
        "Roles" => ScriptRole::class,
    ];

    private static $default_sort = "SortOrder ASC";

    private static $field_labels = [
        "Content"     => "Inhalt",
        "Script"      => "Skript",
        "Roles"       => "Rollen",
        "IsDirection" => "Regieanweisung",
    ];

    private static $summary_fields = [
        "Content" => "Inhalt",
    ];

    private static $table_name = 'ScriptParagraph';
    private static $singular_name = "Skript-Absatz";
    private static $plural_name = "Skript-Absätze";

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Content = $this->sanitizeContent($this->Content ?? '');
    }

    /**
     * Serverseitige Allowlist-Bereinigung des Rich-Text-Inhalts: nur ein enges
     * Set an Tags (siehe ALLOWED_TAGS) bleibt erhalten, alle anderen Tags werden
     * entfernt, verbleibende on*=/style=/href-Attribute werden herausgefiltert.
     * Der Client-Editor ist nicht vertrauenswürdig, daher darf sich das Backend
     * nicht allein auf ihn verlassen.
     */
    private function sanitizeContent(string $html): string
    {
        $stripped = strip_tags($html, self::ALLOWED_TAGS);
        return preg_replace('/\s(on\w+|style|href)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $stripped) ?? $stripped;
    }

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
