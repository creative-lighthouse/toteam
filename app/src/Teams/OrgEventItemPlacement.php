<?php

namespace App\Teams;

use App\Inventory\InventoryItem;
use App\Maps\Map;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\Teams\OrgEventItemPlacement
 *
 * Wo ein für das Event ausgeliehenes Inventar-Objekt steht und was dazu wichtig
 * ist — nur für dieses eine Event (z.B. die DMX-Adresse eines Scheinwerfers).
 * Je Event und Objekt höchstens ein Eintrag; ohne `Map` ist das Objekt (noch)
 * auf keinem Lageplan platziert, die Notiz bleibt trotzdem erhalten.
 *
 * Die Koordinaten haben dasselbe Format wie `MapPOI.Coordinates` ("lat,lng"),
 * damit der Lageplan-Renderer sie wie Marker zeichnen kann.
 *
 * @property ?string $Coordinates
 * @property ?string $Note
 * @property ?string $MarkerText
 * @property int $EventID
 * @property int $ItemID
 * @property int $MapID
 * @method \App\Teams\OrgEvent Event()
 * @method \App\Inventory\InventoryItem Item()
 * @method \App\Maps\Map Map()
 */
class OrgEventItemPlacement extends DataObject
{
    private static $db = [
        "Coordinates" => "Varchar(100)",
        "Note"        => "Text",
        // Text im Marker (wie MapPOI.MarkerText, max. 4 Zeichen) — leer: laufende Nummer
        "MarkerText"  => "Varchar(4)",
    ];

    private static $has_one = [
        "Event" => OrgEvent::class,
        "Item"  => InventoryItem::class,
        "Map"   => Map::class,
    ];

    private static $indexes = [
        "EventItem" => [
            "type"    => "unique",
            "columns" => ["EventID", "ItemID"],
        ],
    ];

    private static $field_labels = [
        "Coordinates" => "Koordinaten",
        "Note"        => "Notiz",
        "MarkerText"  => "Marker-Text",
        "Event"       => "Event",
        "Item"        => "Objekt",
        "Map"         => "Lageplan",
    ];

    private static $summary_fields = [
        "Item.Title"  => "Objekt",
        "Map.Title"   => "Lageplan",
        "Note"        => "Notiz",
    ];

    private static $table_name = 'OrgEventItemPlacement';
    private static $singular_name = "Platzierung";
    private static $plural_name = "Platzierungen";

    /** Auf einem (noch vorhandenen) Lageplan platziert */
    public function isPlaced(): bool
    {
        return $this->MapID && $this->Coordinates && $this->Map()->exists();
    }
}
