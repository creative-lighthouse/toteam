<?php

namespace App\Inventory;

/**
 * Werte der Zusatzfelder einer Art ({@see InventoryTypeField}) als JSON in der
 * Spalte `MetaValues`: { "<Feld-ID>": "<Wert>" }. Genutzt von Objekten und Räumen.
 *
 * @property ?string $MetaValues
 */
trait HasMetaValues
{
    /** @return array<int, string> Feld-ID => gespeicherter Wert */
    public function getMetaValueMap(): array
    {
        $values = json_decode((string) $this->MetaValues, true);
        if (!is_array($values)) {
            return [];
        }
        $map = [];
        foreach ($values as $fieldID => $value) {
            if ($value !== null && $value !== '') {
                $map[(int) $fieldID] = (string) $value;
            }
        }
        return $map;
    }

    /** @param array<int, ?string> $values Feld-ID => Wert (null/leer entfernt den Wert) */
    public function setMetaValueMap(array $values): void
    {
        $clean = [];
        foreach ($values as $fieldID => $value) {
            if ($value !== null && $value !== '') {
                $clean[(string) (int) $fieldID] = (string) $value;
            }
        }
        ksort($clean);
        $this->MetaValues = $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Übernimmt Werte aus einer Eingabe ({ "<Feld-ID>": "<Wert>" }) für die Felder
     * von $type — normalisiert je Format. Werte von Feldern, die die Art nicht hat,
     * bleiben unangetastet (z.B. nach einem Wechsel der Art).
     *
     * @return array<int, array{0: InventoryTypeField, 1: ?string, 2: ?string}> geänderte Felder [Feld, alt, neu]
     */
    public function applyMetaValues(InventoryItemType $type, array $input): array
    {
        $values = $this->getMetaValueMap();
        $changes = [];
        foreach ($type->Fields() as $field) {
            // JSON-Schlüssel "12" werden in PHP-Arrays automatisch zu int 12
            if (!array_key_exists($field->ID, $input)) {
                continue;
            }
            $new = $field->normalizeValue($input[$field->ID]);
            $old = $values[$field->ID] ?? null;
            if ($new === $old) {
                continue;
            }
            $values[$field->ID] = $new;
            $changes[] = [$field, $old, $new];
        }
        $this->setMetaValueMap($values);
        return $changes;
    }
}
