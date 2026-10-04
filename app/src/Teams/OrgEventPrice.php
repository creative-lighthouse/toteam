<?php

namespace App\Teams;

use SilverStripe\ORM\DataObject;

/**
 * Class \App\Teams\OrgEventPrice
 *
 * Eine Zeile der Preistabelle eines Events (z.B. "Erwachsene – 8,00 €").
 *
 * @property ?string $Title
 * @property float $Price
 * @property int $SortOrder
 * @property int $EventID
 * @method \App\Teams\OrgEvent Event()
 */
class OrgEventPrice extends DataObject
{
    private static $db = [
        "Title"     => "Varchar(255)",
        "Price"     => "Decimal(10,2)",
        "SortOrder" => "Int",
    ];

    private static $has_one = [
        "Event" => OrgEvent::class,
    ];

    private static $default_sort = "SortOrder ASC";

    private static $field_labels = [
        "Title" => "Bezeichnung",
        "Price" => "Preis (€)",
    ];

    private static $summary_fields = [
        "Title" => "Bezeichnung",
        "Price" => "Preis (€)",
    ];

    private static $table_name = 'OrgEventPrice';
    private static $singular_name = "Preis";
    private static $plural_name = "Preise";

    public function toApi(): array
    {
        return [
            'ID'    => $this->ID,
            'Title' => $this->Title,
            'Price' => (float) $this->Price,
        ];
    }
}
