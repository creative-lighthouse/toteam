<?php

namespace App\History;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\FieldType\DBTime;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Protokolliert Änderungen an einem Datenobjekt als {@see HistoryEntry}.
 *
 * Einbinden per YAML und im Modell konfigurieren, welche Felder erfasst werden:
 *
 *     App\Tasks\Task:
 *       extensions:
 *         - App\History\HistoryExtension
 *
 *     // im Modell
 *     private static $history_fields = ['Title', 'Description', 'State', 'Owner'];
 *     private static $history_value_labels = ['State' => ['open' => 'Offen', ...]];
 *     private static $history_field_labels = ['Notes' => 'Notiz']; // optional, sonst fieldLabel()
 *     private static $history_created_fields = ['User'];            // optional: Anfangswerte im "erstellt"-Eintrag
 *
 * `history_fields` darf DB-Felder und has_one-Relationen (ohne "ID") enthalten, sie
 * werden beim Schreiben automatisch erfasst. many_many-Relationen werden nicht über
 * write() geschrieben und müssen daher explizit mit {@see trackHistoryRelation()}
 * (oder {@see recordHistorySetChange()}) protokolliert werden.
 *
 * Für eigene Anzeigewerte (z. B. Beträge als "12,50 €") kann das Modell optional
 *     public function getHistoryValueLabel(string $field, $value): ?string
 * definieren — gibt es null zurück, greift die Standard-Formatierung.
 *
 * Unterobjekte ohne eigenen Verlauf (z. B. Teilnahmen an einem Termin) können ihre
 * Änderungen stattdessen in den Verlauf eines übergeordneten Objekts schreiben:
 *
 *     private static $history_target = 'Parent';          // has_one zum Zielobjekt (braucht selbst die Extension)
 *     private static $history_delete_fields = ['Type'];   // beim Löschen protokollierte Felder (Standard: alle)
 *     private static $history_target_set = 'AgendaPoints'; // optional, s. u.
 *     public function getHistoryContextLabel(): ?string   // optional, z. B. Name des Teilnehmers
 *
 * Anlegen wird dann als Änderung "leer → Wert", Löschen als "Wert → leer" im Verlauf
 * des Zielobjekts festgehalten. Ist `history_target_set` gesetzt, erscheinen Anlegen
 * und Löschen stattdessen als Hinzufügen/Entfernen in dieser Liste des Zielobjekts
 * (z. B. "Tagesordnungspunkte: + Begrüßung"), Änderungen weiterhin als Wertänderung.
 *
 * @property DataObject|HistoryExtension $owner
 */
class HistoryExtension extends Extension
{
    private bool $historyIsNew = false;

    protected function onBeforeWrite(): void
    {
        $this->historyIsNew = !$this->owner->isInDB();
    }

    protected function onAfterWrite(): void
    {
        $isNew = $this->historyIsNew;
        $this->historyIsNew = false;

        if ($isNew && !$this->isHistoryChild()) {
            HistoryEntry::recordCreated($this->owner, $this->getHistoryMember(), $this->buildCreatedChanges());
            return;
        }

        $target = $this->getHistoryTarget();
        if ($target && $isNew && ($setField = $this->owner->config()->get('history_target_set'))) {
            $target->recordHistorySetChange($setField, [$this->owner], []);
            return;
        }

        $fieldMap = $this->getHistoryFieldMap();
        if (!$target || !$fieldMap) {
            return;
        }

        $changed = $this->owner->getChangedFields(array_keys($fieldMap), DataObject::CHANGE_VALUE);
        $changes = [];
        foreach ($changed as $dbField => $values) {
            // Beim Anlegen eines Unterobjekts nur tatsächlich gesetzte Werte zeigen,
            // nicht z. B. "Eigene Uhrzeit: leer → Nein"
            if ($isNew && $this->isEmptyHistoryValue($values['after'])) {
                continue;
            }
            $changes[] = $this->buildValueChange($fieldMap[$dbField], $dbField, $isNew ? null : $values['before'], $values['after']);
        }

        HistoryEntry::recordChanges($target, $changes, $this->getHistoryMember());
    }

    protected function onAfterDelete(): void
    {
        if (!$this->isHistoryChild()) {
            foreach (HistoryEntry::getForRecord($this->owner) as $entry) {
                $entry->delete();
            }
            return;
        }

        $target = $this->getHistoryTarget();
        if (!$target) {
            return;
        }

        if ($setField = $this->owner->config()->get('history_target_set')) {
            $target->recordHistorySetChange($setField, [], [$this->owner]);
            return;
        }

        $fieldMap = $this->getHistoryFieldMap();
        $deleteFields = $this->owner->config()->get('history_delete_fields');
        $changes = [];
        foreach ($fieldMap as $dbField => $name) {
            if (is_array($deleteFields) && !in_array($name, $deleteFields, true)) {
                continue;
            }
            $value = $this->owner->getField($dbField);
            if (!$this->isEmptyHistoryValue($value)) {
                $changes[] = $this->buildValueChange($name, $dbField, $value, null);
            }
        }

        HistoryEntry::recordChanges($target, $changes, $this->getHistoryMember());
    }

    /**
     * Führt $callback aus und protokolliert, welche Einträge der (many_many-)Relation
     * dadurch hinzugekommen oder weggefallen sind.
     */
    public function trackHistoryRelation(string $relation, callable $callback): void
    {
        $before = array_map('intval', $this->owner->$relation()->column('ID'));
        $callback();
        $after = array_map('intval', $this->owner->$relation()->column('ID'));

        $class = $this->owner->getRelationClass($relation);
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        $this->recordHistorySetChange(
            $relation,
            $added ? DataObject::get($class)->byIDs($added)->toArray() : [],
            $removed ? DataObject::get($class)->byIDs($removed)->toArray() : []
        );
    }

    /**
     * Protokolliert, dass einer Liste (z. B. Relation oder Unteraufgaben) Objekte
     * hinzugefügt bzw. aus ihr entfernt wurden.
     *
     * @param DataObject[] $added
     * @param DataObject[] $removed
     */
    public function recordHistorySetChange(string $field, array $added, array $removed, ?string $label = null): void
    {
        $toItem = fn (DataObject $obj) => ['ID' => $obj->ID, 'Label' => $this->getHistoryObjectLabel($obj)];

        HistoryEntry::recordChanges($this->owner, [[
            'Field'   => $field,
            'Label'   => $label ?? $this->getHistoryFieldLabel($field),
            'Kind'    => 'set',
            'Added'   => array_map($toItem, $added),
            'Removed' => array_map($toItem, $removed),
        ]], $this->getHistoryMember());
    }

    /**
     * Protokolliert eine freie Wertänderung, die sich nicht über ein eigenes Feld
     * abbilden lässt (z. B. Status eines verknüpften Objekts). $field muss pro
     * Sachverhalt eindeutig sein, damit schnelle Folgeänderungen zusammengeführt werden.
     */
    public function recordHistoryValueChange(string $field, string $label, ?string $old, ?string $new, string $format = 'text'): void
    {
        HistoryEntry::recordChanges($this->owner, [[
            'Field'  => $field,
            'Label'  => $label,
            'Kind'   => 'value',
            'Format' => $format,
            'Old'    => $old,
            'New'    => $new,
        ]], $this->getHistoryMember());
    }

    public function getHistory()
    {
        return HistoryEntry::getForRecord($this->owner);
    }

    private function isHistoryChild(): bool
    {
        return (bool) $this->owner->config()->get('history_target');
    }

    /**
     * Das Objekt, in dessen Verlauf protokolliert wird: das Objekt selbst oder, bei
     * Unterobjekten, das über `history_target` verknüpfte Objekt.
     */
    private function getHistoryTarget(): ?DataObject
    {
        $relation = $this->owner->config()->get('history_target');
        if (!$relation) {
            return $this->owner;
        }

        $target = $this->owner->getComponent($relation);
        return $target && $target->exists() && $target->hasExtension(self::class) ? $target : null;
    }

    private function buildValueChange(string $name, string $dbField, $before, $after): array
    {
        [$format, $old] = $this->formatHistoryValue($name, $dbField, $before);
        [, $new] = $this->formatHistoryValue($name, $dbField, $after);

        $field = $name;
        $label = $this->getHistoryFieldLabel($name);
        if ($this->isHistoryChild()) {
            // Pro Unterobjekt eindeutig, damit z. B. die Zusagen zweier Mitglieder
            // nicht miteinander verschmolzen werden
            $field = $this->owner->ClassName . '#' . $this->owner->ID . '.' . $name;
            $context = $this->owner->hasMethod('getHistoryContextLabel') ? $this->owner->getHistoryContextLabel() : null;
            if ($context) {
                $label .= ' (' . $context . ')';
            }
        }

        return [
            'Field'  => $field,
            'Label'  => $label,
            'Kind'   => 'value',
            'Format' => $format,
            'Old'    => $old,
            'New'    => $new,
        ];
    }

    /**
     * Anfangswerte der in `history_created_fields` genannten Felder für den
     * "erstellt"-Eintrag (z. B. für wen eine Buchung erfasst wurde).
     */
    private function buildCreatedChanges(): array
    {
        $createdFields = $this->owner->config()->get('history_created_fields') ?: [];
        $changes = [];
        foreach ($this->getHistoryFieldMap() as $dbField => $name) {
            if (!in_array($name, $createdFields, true)) {
                continue;
            }
            $value = $this->owner->getField($dbField);
            if (!$this->isEmptyHistoryValue($value)) {
                $changes[] = $this->buildValueChange($name, $dbField, null, $value);
            }
        }
        return $changes;
    }

    private function getHistoryFieldLabel(string $name): string
    {
        return $this->owner->config()->get('history_field_labels')[$name] ?? $this->owner->fieldLabel($name);
    }

    /**
     * DB-Feldname => konfigurierter Name (has_one "Owner" wird zu "OwnerID" => "Owner").
     */
    private function getHistoryFieldMap(): array
    {
        $schema = $this->owner->getSchema();
        $map = [];
        foreach ($this->owner->config()->get('history_fields') ?: [] as $name) {
            $dbField = $schema->hasOneComponent($this->owner->ClassName, $name) ? $name . 'ID' : $name;
            $map[$dbField] = $name;
        }
        return $map;
    }

    private function isEmptyHistoryValue($value): bool
    {
        return $value === null || $value === '' || $value === false || $value === 0 || $value === '0';
    }

    /**
     * Wandelt einen Rohwert in einen anzeigbaren Wert um. Gespeichert wird der
     * Anzeigewert zum Zeitpunkt der Änderung, damit der Verlauf auch dann lesbar
     * bleibt, wenn z. B. ein verknüpftes Mitglied später umbenannt oder gelöscht wird.
     *
     * @return array{0: string, 1: ?string} [Format, Wert]
     */
    private function formatHistoryValue(string $name, string $dbField, $value): array
    {
        if ($value === null || $value === '') {
            $value = null;
        }

        if ($value !== null && $this->owner->hasMethod('getHistoryValueLabel')) {
            $custom = $this->owner->getHistoryValueLabel($name, $value);
            if ($custom !== null) {
                return ['text', $custom];
            }
        }

        if ($dbField !== $name) {
            if (!$value) {
                return ['text', null];
            }
            $class = $this->owner->getRelationClass($name);
            $related = DataObject::get($class)->byID((int) $value);
            return ['text', $related ? $this->getHistoryObjectLabel($related) : '#' . $value];
        }

        $labels = $this->owner->config()->get('history_value_labels')[$name] ?? null;
        if ($labels !== null) {
            if ($value === null) {
                return ['text', $labels[''] ?? null];
            }
            return ['text', $labels[$value] ?? (string) $value];
        }

        $dbObject = $this->owner->dbObject($dbField);
        if ($dbObject instanceof DBBoolean) {
            return ['text', $value ? 'Ja' : 'Nein'];
        }
        if ($dbObject instanceof DBDatetime) {
            return ['datetime', $value === null ? null : date('Y-m-d H:i:s', strtotime((string) $value))];
        }
        if ($dbObject instanceof DBDate) {
            return ['date', $value === null ? null : date('Y-m-d', strtotime((string) $value))];
        }
        if ($dbObject instanceof DBTime) {
            return ['text', $value === null ? null : date('H:i', strtotime((string) $value))];
        }
        if ($dbObject instanceof DBText) {
            return ['longtext', $value === null ? null : (string) $value];
        }

        return ['text', $value === null ? null : (string) $value];
    }

    private function getHistoryObjectLabel(DataObject $obj): string
    {
        return $obj->hasMethod('getDisplayName') ? $obj->getDisplayName() : (string) $obj->getTitle();
    }

    private function getHistoryMember(): ?Member
    {
        return Security::getCurrentUser();
    }
}
