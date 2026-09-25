<?php

namespace App\Inventory;

use SilverStripe\ORM\DataObject;

/**
 * Class \App\Inventory\InventoryTypeField
 *
 * Ein von der Organisation selbst benanntes Zusatzfeld einer Art (z.B.
 * "Kabellänge" in m, "Letzte DGUV-Prüfung" als Datum). Die Werte stehen je
 * Objekt in {@see InventoryItem::$MetaValues}.
 *
 * `Individual` markiert Felder, die je Objekt verschieden sind (Seriennummer,
 * Prüfdatum, …) — sie werden beim Bearbeiten einer ganzen Gruppe gleicher
 * Objekte nicht übertragen und beim Duplizieren nicht kopiert.
 *
 * `IsPublic`: auf der öffentlichen Seite des Objekts (Teilen-Link) auch ohne
 * Anmeldung sichtbar — für nicht datenschutzrelevante Felder.
 * `ShowInList`: Wert erscheint in der Inventarliste (z.B. Kabellänge).
 *
 * @property ?string $Label
 * @property ?string $Format
 * @property bool $Individual
 * @property bool $IsPublic
 * @property bool $ShowInList
 * @property int $SortOrder
 * @property int $TypeID
 * @method \App\Inventory\InventoryItemType Type()
 */
class InventoryTypeField extends DataObject
{
    /**
     * Wählbare Formate: Schlüssel => [Beschriftung, Einheit, Eingabetyp]
     * Eingabetypen: text, textarea, number, date, boolean
     */
    public const FORMATS = [
        'text'     => ['label' => 'Freitext',             'unit' => '',  'input' => 'text'],
        'textarea' => ['label' => 'Freitext (mehrzeilig)', 'unit' => '', 'input' => 'textarea'],
        'number'   => ['label' => 'Zahl',                 'unit' => '',  'input' => 'number'],
        'mm'       => ['label' => 'Millimeter (mm)',      'unit' => 'mm', 'input' => 'number'],
        'cm'       => ['label' => 'Zentimeter (cm)',      'unit' => 'cm', 'input' => 'number'],
        'm'        => ['label' => 'Meter (m)',            'unit' => 'm',  'input' => 'number'],
        'm2'       => ['label' => 'Quadratmeter (m²)',    'unit' => 'm²', 'input' => 'number'],
        'g'        => ['label' => 'Gramm (g)',            'unit' => 'g',  'input' => 'number'],
        'kg'       => ['label' => 'Kilogramm (kg)',       'unit' => 'kg', 'input' => 'number'],
        'l'        => ['label' => 'Liter (l)',            'unit' => 'l',  'input' => 'number'],
        'W'        => ['label' => 'Watt (W)',             'unit' => 'W',  'input' => 'number'],
        'V'        => ['label' => 'Volt (V)',             'unit' => 'V',  'input' => 'number'],
        'A'        => ['label' => 'Ampere (A)',           'unit' => 'A',  'input' => 'number'],
        'lm'       => ['label' => 'Lumen (lm)',           'unit' => 'lm', 'input' => 'number'],
        'eur'      => ['label' => 'Preis (€)',            'unit' => '€',  'input' => 'number'],
        'date'     => ['label' => 'Datum',                'unit' => '',  'input' => 'date'],
        'boolean'  => ['label' => 'Ja/Nein',              'unit' => '',  'input' => 'boolean'],
    ];

    private static $db = [
        "Label"      => "Varchar(100)",
        "Format"     => "Varchar(20)",
        "Individual" => "Boolean",
        "IsPublic"   => "Boolean",
        "ShowInList" => "Boolean",
        "SortOrder"  => "Int",
    ];

    private static $defaults = [
        "Format" => "text",
    ];

    private static $has_one = [
        "Type" => InventoryItemType::class,
    ];

    private static $default_sort = "SortOrder ASC, ID ASC";

    private static $field_labels = [
        "Label"      => "Bezeichnung",
        "Format"     => "Format",
        "Individual" => "Pro Objekt",
        "IsPublic"   => "Öffentlich sichtbar",
        "ShowInList" => "In Listenansicht",
        "SortOrder"  => "Reihenfolge",
        "Type"       => "Art",
    ];

    private static $summary_fields = [
        "Label"      => "Bezeichnung",
        "Format"     => "Format",
        "Individual" => "Pro Objekt",
    ];

    private static $table_name = 'InventoryTypeField';
    private static $singular_name = "Feld";
    private static $plural_name = "Felder";

    public function getTitle()
    {
        return (string) $this->Label;
    }

    public function getInput(): string
    {
        return self::FORMATS[$this->Format]['input'] ?? 'text';
    }

    public function getUnit(): string
    {
        return self::FORMATS[$this->Format]['unit'] ?? '';
    }

    /**
     * Bringt eine Eingabe ins Speicherformat: Zahlen mit Punkt, Datum als
     * Y-m-d, Ja/Nein als "1"/"0", Text getrimmt. Leere Eingaben werden null.
     */
    public function normalizeValue($value): ?string
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            return null;
        }
        switch ($this->getInput()) {
            case 'number':
                $number = str_replace(',', '.', trim((string) $value));
                return is_numeric($number) ? (string) (float) $number : null;
            case 'date':
                $timestamp = strtotime((string) $value);
                return $timestamp === false ? null : date('Y-m-d', $timestamp);
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            default:
                return trim((string) $value);
        }
    }

    /** Anzeigewert für den Verlauf */
    public function displayValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        switch ($this->getInput()) {
            case 'number':
                $formatted = rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
                return trim($formatted . ' ' . $this->getUnit());
            case 'date':
                return date('d.m.Y', strtotime($value));
            case 'boolean':
                return $value === '1' ? 'Ja' : 'Nein';
            default:
                return $value;
        }
    }
}
