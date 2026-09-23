<?php

namespace App\History;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;

/**
 * Ein Eintrag im Änderungsverlauf eines beliebigen Datenobjekts (siehe {@see HistoryExtension}).
 *
 * Ein Eintrag bündelt alle Änderungen, die ein Nutzer innerhalb kurzer Zeit
 * hintereinander gemacht hat. `Changes` ist ein JSON-Array aus Änderungen der Form:
 *   - Wertänderung: {Field, Label, Kind: 'value', Format: 'text'|'longtext'|'date'|'datetime', Old, New}
 *   - Listenänderung: {Field, Label, Kind: 'set', Added: [{ID, Label}], Removed: [{ID, Label}]}
 *
 * @property ?string $Type
 * @property ?string $Changes
 * @property int $RecordID
 * @property ?string $RecordClass
 * @property int $MemberID
 * @method \SilverStripe\ORM\DataObject Record()
 * @method \SilverStripe\Security\Member Member()
 */
class HistoryEntry extends DataObject
{
    /**
     * Zeitfenster in Sekunden: Folgt eine Änderung desselben Nutzers innerhalb dieser
     * Zeit auf dessen letzte Änderung, wird sie in den bestehenden Eintrag übernommen
     * (gleitend — jede Änderung verlängert das Fenster).
     */
    private static int $merge_window = 300;

    private static $db = [
        'Type'    => "Enum('created,changed', 'changed')",
        'Changes' => 'Text',
    ];

    private static $has_one = [
        'Record' => DataObject::class,
        'Member' => Member::class,
    ];

    private static $indexes = [
        'Record' => ['type' => 'index', 'columns' => ['RecordClass', 'RecordID']],
    ];

    private static $table_name = 'HistoryEntry';
    private static $singular_name = 'Verlaufseintrag';
    private static $plural_name = 'Verlaufseinträge';
    private static $default_sort = 'ID DESC';

    public function getChangeList(): array
    {
        return json_decode($this->Changes ?: '[]', true) ?: [];
    }

    public function setChangeList(array $changes): void
    {
        $this->Changes = json_encode(array_values($changes), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Alle Verlaufseinträge eines Datenobjekts, neueste zuerst.
     */
    public static function getForRecord(DataObject $record)
    {
        return static::get()->filter([
            'RecordClass' => $record->ClassName,
            'RecordID'    => $record->ID,
        ]);
    }

    /**
     * Legt einen "erstellt"-Eintrag für das Datenobjekt an.
     */
    public static function recordCreated(DataObject $record, ?Member $member): void
    {
        $entry = static::create();
        $entry->Type = 'created';
        $entry->RecordClass = $record->ClassName;
        $entry->RecordID = $record->ID;
        $entry->MemberID = $member ? $member->ID : 0;
        $entry->setChangeList([]);
        $entry->write();
    }

    /**
     * Protokolliert Änderungen. Ist der jüngste Eintrag des Datenobjekts vom selben
     * Nutzer und liegt innerhalb des {@see $merge_window}, werden die Änderungen dort
     * hineingemischt. Nur der jüngste Eintrag kommt dafür in Frage — hat zwischendurch
     * jemand anderes etwas geändert, beginnt ein neuer Eintrag, damit die Reihenfolge
     * im Verlauf stimmt.
     */
    public static function recordChanges(DataObject $record, array $changes, ?Member $member): void
    {
        $changes = array_values(array_filter($changes, [static::class, 'isEffective']));
        if (!$changes) {
            return;
        }

        $memberID = $member ? $member->ID : 0;
        $latest = static::getForRecord($record)->sort('ID', 'DESC')->first();

        $mergeable = $latest
            && $latest->Type === 'changed'
            && (int) $latest->MemberID === $memberID
            && (DBDatetime::now()->getTimestamp() - strtotime((string) $latest->LastEdited)) <= static::config()->get('merge_window');

        if ($mergeable) {
            $merged = static::mergeChanges($latest->getChangeList(), $changes);
            if (!$merged) {
                // Alles wieder auf den Ausgangszustand zurückgesetzt — nichts mehr zu zeigen
                $latest->delete();
                return;
            }
            $latest->setChangeList($merged);
            // forceWrite, damit LastEdited (und damit das gleitende Fenster) auch dann
            // aktualisiert wird, wenn sich die JSON-Daten zufällig nicht geändert haben
            $latest->write(false, false, true);
            return;
        }

        $entry = static::create();
        $entry->Type = 'changed';
        $entry->RecordClass = $record->ClassName;
        $entry->RecordID = $record->ID;
        $entry->MemberID = $memberID;
        $entry->setChangeList($changes);
        $entry->write();
    }

    /**
     * Mischt neue Änderungen in bestehende: Bei Werten bleibt der ursprüngliche
     * Vorher-Wert erhalten und nur der Nachher-Wert wird ersetzt, bei Listen heben
     * sich Hinzufügen und Entfernen desselben Elements gegenseitig auf. Änderungen,
     * die dadurch wirkungslos werden, fallen weg.
     */
    public static function mergeChanges(array $existing, array $incoming): array
    {
        $byField = [];
        foreach ($existing as $change) {
            $byField[$change['Field']] = $change;
        }

        foreach ($incoming as $change) {
            $field = $change['Field'];
            if (!isset($byField[$field])) {
                $byField[$field] = $change;
                continue;
            }

            $current = $byField[$field];
            if ($change['Kind'] === 'set') {
                foreach ($change['Added'] as $item) {
                    static::toggleSetItem($current['Removed'], $current['Added'], $item);
                }
                foreach ($change['Removed'] as $item) {
                    static::toggleSetItem($current['Added'], $current['Removed'], $item);
                }
            } else {
                $current['New'] = $change['New'];
            }
            $current['Label'] = $change['Label'];
            $byField[$field] = $current;
        }

        return array_values(array_filter($byField, [static::class, 'isEffective']));
    }

    /**
     * Entfernt $item aus $opposite, falls dort vorhanden (hebt sich auf),
     * ansonsten wird es zu $target hinzugefügt.
     */
    private static function toggleSetItem(array &$opposite, array &$target, array $item): void
    {
        foreach ($opposite as $i => $existing) {
            if ($existing['ID'] == $item['ID']) {
                array_splice($opposite, $i, 1);
                return;
            }
        }
        foreach ($target as $existing) {
            if ($existing['ID'] == $item['ID']) {
                return;
            }
        }
        $target[] = $item;
    }

    private static function isEffective(array $change): bool
    {
        if ($change['Kind'] === 'set') {
            return !empty($change['Added']) || !empty($change['Removed']);
        }
        return $change['Old'] !== $change['New'];
    }

    public function toApi(): array
    {
        $member = $this->Member();

        return [
            'ID'      => $this->ID,
            'Type'    => $this->Type,
            'Date'    => date('c', strtotime((string) $this->LastEdited)),
            'Member'  => $member && $member->exists() ? [
                'ID'     => $member->ID,
                'Name'   => $member->getDisplayName(),
                'Avatar' => $member->RenderProfileImage(),
            ] : null,
            'Changes' => $this->getChangeList(),
        ];
    }
}
