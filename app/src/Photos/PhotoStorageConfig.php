<?php

namespace App\Photos;

use App\Teams\Organization;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\Photos\PhotoStorageConfig
 *
 * Bunny-Zugang einer Organisation für das Foto-Totem. Jede Organisation hat einen
 * eigenen Bunny-Account (und damit ihre eigene Abrechnung): eine Storage Zone für die
 * Dateien und eine daran hängende Pull Zone, über die die Bilder ausgeliefert werden.
 * Die Schlüssel verlassen den Server nie — {@see toApi()} meldet nur, ob sie gesetzt sind.
 *
 * @property ?string $StorageZone
 * @property ?string $StorageRegion
 * @property ?string $AccessKey
 * @property ?string $PullZoneURL
 * @property ?string $TokenKey
 * @property int $OrganizationID
 * @method \App\Teams\Organization Organization()
 */
class PhotoStorageConfig extends DataObject
{
    /**
     * Speicher-Regionen von Bunny Storage: Hostname => Anzeigename.
     * Muss zur Region passen, in der die Storage Zone angelegt wurde.
     */
    public const REGIONS = [
        'storage.bunnycdn.com'     => 'Falkenstein (DE)',
        'uk.storage.bunnycdn.com'  => 'London (UK)',
        'se.storage.bunnycdn.com'  => 'Stockholm (SE)',
        'ny.storage.bunnycdn.com'  => 'New York (US)',
        'la.storage.bunnycdn.com'  => 'Los Angeles (US)',
        'sg.storage.bunnycdn.com'  => 'Singapur (SG)',
        'syd.storage.bunnycdn.com' => 'Sydney (AU)',
        'br.storage.bunnycdn.com'  => 'São Paulo (BR)',
        'jh.storage.bunnycdn.com'  => 'Johannesburg (ZA)',
    ];

    private static $db = [
        // Name der Storage Zone
        "StorageZone"   => "Varchar(100)",
        // Hostname der Region (Schlüssel aus REGIONS)
        "StorageRegion" => "Varchar(100)",
        // Storage-Zone-Passwort ("FTP & API Access" → Password), nur schreibbar
        "AccessKey"     => "Varchar(255)",
        // Basis-URL der Pull Zone, z.B. https://mein-verein.b-cdn.net
        "PullZoneURL"   => "Varchar(255)",
        // Optional: "Token Authentication"-Schlüssel der Pull Zone → signierte, ablaufende Links
        "TokenKey"      => "Varchar(255)",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
    ];

    private static $indexes = [
        "OrganizationID" => ["type" => "unique", "columns" => ["OrganizationID"]],
    ];

    private static $defaults = [
        "StorageRegion" => "storage.bunnycdn.com",
    ];

    private static $table_name = 'PhotoStorageConfig';
    private static $singular_name = "Foto-Speicher";
    private static $plural_name = "Foto-Speicher";

    public static function forOrganization(int $orgID): ?self
    {
        return $orgID ? self::get()->filter('OrganizationID', $orgID)->first() : null;
    }

    /** Alles da, um hochzuladen und auszuliefern */
    public function isComplete(): bool
    {
        return $this->StorageZone && $this->AccessKey && $this->PullZoneURL
            && isset(self::REGIONS[$this->StorageRegion]);
    }

    public function client(): BunnyStorage
    {
        return new BunnyStorage($this);
    }

    /** Für das Einstellungs-Formular — ohne Schlüssel */
    public function toApi(): array
    {
        return [
            'StorageZone'   => (string) $this->StorageZone,
            'StorageRegion' => (string) ($this->StorageRegion ?: 'storage.bunnycdn.com'),
            'PullZoneURL'   => (string) $this->PullZoneURL,
            'HasAccessKey'  => (bool) $this->AccessKey,
            'HasTokenKey'   => (bool) $this->TokenKey,
            'IsComplete'    => $this->isComplete(),
        ];
    }

    public function canView($member = null, $context = [])
    {
        return false; // nur über die API, nie im CMS-Export o.ä.
    }
}
