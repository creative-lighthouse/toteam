<?php

namespace App\History;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBText;
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
 *
 * `history_fields` darf DB-Felder und has_one-Relationen (ohne "ID") enthalten, sie
 * werden beim Schreiben automatisch erfasst. many_many-Relationen werden nicht über
 * write() geschrieben und müssen daher explizit mit {@see trackHistoryRelation()}
 * (oder {@see recordHistorySetChange()}) protokolliert werden.
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
        if ($this->historyIsNew) {
            $this->historyIsNew = false;
            HistoryEntry::recordCreated($this->owner, $this->getHistoryMember());
            return;
        }

        $fieldMap = $this->getHistoryFieldMap();
        if (!$fieldMap) {
            return;
        }

        $changed = $this->owner->getChangedFields(array_keys($fieldMap), DataObject::CHANGE_VALUE);
        $changes = [];
        foreach ($changed as $dbField => $values) {
            $name = $fieldMap[$dbField];
            [$format, $old] = $this->formatHistoryValue($name, $dbField, $values['before']);
            [, $new] = $this->formatHistoryValue($name, $dbField, $values['after']);
            $changes[] = [
                'Field'  => $name,
                'Label'  => $this->owner->fieldLabel($name),
                'Kind'   => 'value',
                'Format' => $format,
                'Old'    => $old,
                'New'    => $new,
            ];
        }

        HistoryEntry::recordChanges($this->owner, $changes, $this->getHistoryMember());
    }

    protected function onAfterDelete(): void
    {
        foreach (HistoryEntry::getForRecord($this->owner) as $entry) {
            $entry->delete();
        }
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
            'Label'   => $label ?? $this->owner->fieldLabel($field),
            'Kind'    => 'set',
            'Added'   => array_map($toItem, $added),
            'Removed' => array_map($toItem, $removed),
        ]], $this->getHistoryMember());
    }

    public function getHistory()
    {
        return HistoryEntry::getForRecord($this->owner);
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
            return ['text', $value === null ? null : ($labels[$value] ?? (string) $value)];
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
