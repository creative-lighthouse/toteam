<?php

namespace App\Photos;

use App\Teams\Organization;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\Photos\PhotoTag
 *
 * Freies Schlagwort für Fotos (z.B. "Aufbau", "Gruppenfoto", "Requisite"), je Organisation.
 * Wird beim Taggen bei Bedarf angelegt; nicht mehr benutzte Schlagworte räumt
 * {@see pruneUnused()} weg.
 *
 * @property ?string $Title
 * @property int $OrganizationID
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\ORM\ManyManyList|\App\Photos\Photo[] Photos()
 */
class PhotoTag extends DataObject
{
    private static $db = [
        "Title" => "Varchar(60)",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
    ];

    private static $belongs_many_many = [
        "Photos" => Photo::class . '.Tags',
    ];

    private static $indexes = [
        "OrgTitle" => ["type" => "unique", "columns" => ["OrganizationID", "Title"]],
    ];

    private static $default_sort = "Title ASC";

    private static $table_name = 'PhotoTag';
    private static $singular_name = "Foto-Schlagwort";
    private static $plural_name = "Foto-Schlagworte";

    /** Schlagwort der Organisation holen oder anlegen (Groß-/Kleinschreibung egal) */
    public static function findOrCreate(int $orgID, string $title): ?self
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        if ($title === '') {
            return null;
        }
        $title = mb_substr($title, 0, 60);
        $existing = self::get()->filter(['OrganizationID' => $orgID, 'Title:nocase' => $title])->first();
        if ($existing) {
            return $existing;
        }
        $tag = self::create(['Title' => $title, 'OrganizationID' => $orgID]);
        $tag->write();
        return $tag;
    }

    /** Schlagworte ohne Fotos löschen */
    public static function pruneUnused(int $orgID): void
    {
        foreach (self::get()->filter('OrganizationID', $orgID) as $tag) {
            if (!$tag->Photos()->exists()) {
                $tag->delete();
            }
        }
    }
}
