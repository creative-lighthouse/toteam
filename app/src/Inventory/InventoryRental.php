<?php

namespace App\Inventory;

use App\Calendar\Appointment;
use App\Rooms\Room;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Inventory\InventoryRental
 *
 * Ein Ausleih-Antrag für ein oder mehrere Objekte und/oder Räume einer
 * Organisation (Räume nur, wenn sie als reservierbar markiert sind).
 *
 * Ablauf: `requested` → `approved` / `rejected` (durch ein Mitglied mit
 * INVENTORY_APPROVE_RENTALS, auch beim eigenen Antrag) → `handed_over` → `returned`. Beantragte oder
 * genehmigte Ausleihen können storniert werden (`cancelled`).
 *
 * Die Organisation (`Organization`) ist der Kontext der Ausleihe, also für wen
 * ausgeliehen wird — leer bei Ausleihen für private Zwecke (nur Objekte mit
 * `PrivateRentable`). Die Quelle ist entweder eine Organisation
 * (`LenderOrganization`, deren Mitglieder mit INVENTORY_APPROVE_RENTALS
 * entscheiden — bei freigegebenen Objekten auch eine andere als der Kontext)
 * oder ein privater Besitzer (`Lender`, dann entscheidet nur er). Eine Ausleihe
 * enthält immer nur Objekte *einer* Quelle; gemischte Anträge teilt der
 * Controller beim Anlegen auf.
 *
 * Beantragt wird eine Anzahl je Gruppe gleicher Objekte; reserviert werden dabei
 * konkrete, freie Objekte der Gruppe (`Items`). Wer verleiht, kann sie gegen
 * andere freie Objekte derselben Gruppe tauschen (siehe swapItem()).
 *
 * Bei der Genehmigung wird eine Auflage (`UsageCondition`) festgelegt: frei nutzbar,
 * nur unter einer Bedingung (`ConditionComment`) oder nicht benutzen (z.B. nur
 * transportieren/lagern).
 *
 * @property ?string $StartDate
 * @property ?string $EndDate
 * @property ?string $Status
 * @property ?string $Purpose
 * @property ?string $UsageCondition
 * @property ?string $ConditionComment
 * @property ?string $DecisionComment
 * @property ?string $DecidedAt
 * @property ?string $HandedOverAt
 * @property ?string $ReturnedAt
 * @property int $OrganizationID
 * @property int $MemberID
 * @property int $DecidedByID
 * @property int $LenderID
 * @property int $LenderOrganizationID
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\Security\Member Member()
 * @method \SilverStripe\Security\Member DecidedBy()
 * @method \SilverStripe\Security\Member Lender()
 * @method \App\Teams\Organization LenderOrganization()
 * @method \SilverStripe\ORM\ManyManyList|\App\Inventory\InventoryItem[] Items()
 * @method \SilverStripe\ORM\DataList|\App\Inventory\InventoryDamageReport[] DamageReports()
 * @method \SilverStripe\ORM\ManyManyList|\App\Rooms\Room[] Rooms()
 * @method \SilverStripe\ORM\ManyManyList|\App\Calendar\Appointment[] Events()
 * @mixin \App\History\HistoryExtension
 */
class InventoryRental extends DataObject
{
    public const STATUS_LABELS = [
        'requested'   => 'Beantragt',
        'approved'    => 'Genehmigt',
        'rejected'    => 'Abgelehnt',
        'handed_over' => 'Übergeben',
        'returned'    => 'Zurückgegeben',
        'cancelled'   => 'Storniert',
    ];

    public const CONDITION_LABELS = [
        'free'        => 'Frei nutzbar',
        'conditional' => 'Nur unter Bedingung',
        'do_not_use'  => 'Nicht benutzen',
    ];

    /** Status, in denen Objekte und Räume für den Zeitraum belegt sind. */
    public const BLOCKING_STATUSES = ['approved', 'handed_over'];

    private static $db = [
        "StartDate"        => "Date",
        "EndDate"          => "Date",
        "Status"           => "Enum('requested,approved,rejected,handed_over,returned,cancelled','requested')",
        "Purpose"          => "Text",
        "UsageCondition"   => "Enum('free,conditional,do_not_use','free')",
        "ConditionComment" => "Text",
        "DecisionComment"  => "Text",
        "DecidedAt"        => "Datetime",
        "HandedOverAt"     => "Datetime",
        "ReturnedAt"       => "Datetime",
    ];

    private static $defaults = [
        "Status"         => "requested",
        "UsageCondition" => "free",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
        "Member"       => Member::class,
        "DecidedBy"    => Member::class,
        "Lender"       => Member::class,
        // Besitzende Organisation (leer bei älteren Ausleihen → Organization)
        "LenderOrganization" => Organization::class,
    ];

    private static $has_many = [
        "DamageReports" => InventoryDamageReport::class . '.Rental',
    ];

    private static $cascade_deletes = [
        "DamageReports",
    ];

    private static $many_many = [
        "Items"  => InventoryItem::class,
        "Rooms"  => Room::class,
        "Events" => Appointment::class,
    ];

    /**
     * Kilometerstand je Fahrzeug bei Übergabe und Rückgabe (0 = nicht erfasst).
     * Für normale Objekte ungenutzt.
     */
    private static $many_many_extraFields = [
        "Items" => [
            "StartMileage" => "Int",
            "EndMileage"   => "Int",
        ],
    ];


    private static $default_sort = "StartDate DESC";

    private static $field_labels = [
        "StartDate"        => "Von",
        "EndDate"          => "Bis",
        "Status"           => "Status",
        "Purpose"          => "Zweck",
        "UsageCondition"   => "Auflage",
        "ConditionComment" => "Bedingung",
        "DecisionComment"  => "Kommentar zur Entscheidung",
        "DecidedAt"        => "Entschieden am",
        "HandedOverAt"     => "Übergeben am",
        "ReturnedAt"       => "Zurückgegeben am",
        "Organization"     => "Organisation",
        "Member"           => "Ausleihende Person",
        "DecidedBy"        => "Entschieden von",
        "Lender"           => "Verleiht (privat)",
        "LenderOrganization" => "Verleihende Organisation",
        "Items"            => "Objekte",
        "Rooms"            => "Räume",
        "Events"           => "Termine",
        "DamageReports"    => "Schadensmeldungen",
    ];

    private static $summary_fields = [
        "StartDate"          => "Von",
        "EndDate"            => "Bis",
        "Member.Name"        => "Ausleihende Person",
        "Organization.Title" => "Organisation",
        "Status"             => "Status",
    ];

    private static $history_fields = [
        'StartDate',
        'EndDate',
        'Status',
        'Purpose',
        'UsageCondition',
        'ConditionComment',
        'DecisionComment',
    ];

    private static $history_value_labels = [
        'Status'    => self::STATUS_LABELS,
        'UsageCondition' => self::CONDITION_LABELS,
    ];

    private static $table_name = 'InventoryRental';
    private static $singular_name = "Ausleihe";
    private static $plural_name = "Ausleihen";

    public function getTitle()
    {
        return 'Ausleihe ' . $this->StartDate . ' – ' . $this->EndDate . ($this->isInDB() ? ' (' . $this->getContentSummary() . ')' : '');
    }

    /** z.B. "3 Objekte, 1 Raum" */
    public function getContentSummary(): string
    {
        $items = $this->Items()->count();
        $rooms = $this->Rooms()->count();
        $parts = [];
        if ($items) {
            $parts[] = $items . ' Objekt' . ($items === 1 ? '' : 'e');
        }
        if ($rooms) {
            $parts[] = $rooms . ' Raum' . ($rooms === 1 ? '' : 'e');
        }
        return $parts ? implode(', ', $parts) : 'leer';
    }

    /**
     * Andere belegende Ausleihen (genehmigt/übergeben), die sich zeitlich mit
     * [$start, $end] überschneiden und mindestens einen der Datensätze der
     * Relation enthalten.
     *
     * @param string $relation 'Items' oder 'Rooms'
     * @param int[] $ids
     */
    public static function findConflicts(string $relation, array $ids, string $start, string $end, int $excludeRentalID = 0): DataList
    {
        return self::get()->filter([
            'Status'                     => self::BLOCKING_STATUSES,
            'StartDate:LessThanOrEqual'  => $end,
            'EndDate:GreaterThanOrEqual' => $start,
            $relation . '.ID'            => $ids ?: [0],
        ])->exclude('ID', $excludeRentalID ?: 0);
    }

    /** @return array{StartMileage: int, EndMileage: int} erfasste Kilometerstände eines Fahrzeugs */
    public function getMileage(InventoryItem $item): array
    {
        // Bewusst aus der Verknüpfung lesen: ein $item aus der Items()-Liste einer
        // *anderen* Ausleihe trüge deren Werte
        $extra = $this->Items()->getExtraData('Items', $item->ID);
        return [
            'StartMileage' => (int) ($extra['StartMileage'] ?? 0),
            'EndMileage'   => (int) ($extra['EndMileage'] ?? 0),
        ];
    }

    /**
     * Objekte bzw. Räume dieser Ausleihe, die im Zeitraum nicht (mehr) verfügbar
     * sind: in einer anderen genehmigten Ausleihe belegt bzw. — bei Objekten —
     * inzwischen nicht mehr einsatzbereit.
     *
     * @param string $relation 'Items' oder 'Rooms'
     * @return DataObject[]
     */
    public function getConflicting(string $relation): array
    {
        if ($relation === 'Items') {
            return array_values(array_filter(
                $this->Items()->toArray(),
                fn (InventoryItem $item) => !$item->isFreeIn($this->StartDate, $this->EndDate, $this->ID)
            ));
        }

        $ids = array_map('intval', $this->$relation()->column('ID'));
        if (!$ids) {
            return [];
        }
        $conflicting = [];
        foreach (self::findConflicts($relation, $ids, $this->StartDate, $this->EndDate, $this->ID) as $other) {
            foreach ($other->$relation()->filter('ID', $ids) as $record) {
                $conflicting[$record->ID] = $record;
            }
        }
        return array_values($conflicting);
    }

    /**
     * Freie Objekte derselben Gruppe, die statt $item reserviert werden könnten.
     *
     * @return InventoryItem[]
     */
    public function getAlternativesFor(InventoryItem $item): array
    {
        $inRental = array_map('intval', $this->Items()->column('ID'));
        return array_values(array_filter(
            $item->getGroupItems()->exclude('ID', $inRental ?: [0])->toArray(),
            fn (InventoryItem $candidate) => $candidate->isFreeIn($this->StartDate, $this->EndDate, $this->ID)
        ));
    }

    /**
     * Tauscht ein reserviertes Objekt gegen ein anderes derselben Gruppe.
     * Gibt einen Fehlertext zurück oder null bei Erfolg.
     */
    public function swapItem(InventoryItem $old, InventoryItem $new): ?string
    {
        if (!$this->Items()->byID($old->ID)) {
            return 'Das Objekt gehört nicht zu dieser Ausleihe';
        }
        if ($old->GroupKey !== $new->GroupKey) {
            return 'Es kann nur gegen ein gleiches Objekt getauscht werden';
        }
        if ($this->Items()->byID($new->ID) || !$new->isFreeIn($this->StartDate, $this->EndDate, $this->ID)) {
            return '"' . $new->InventoryNumber . '" ist im Zeitraum nicht verfügbar';
        }
        $this->Items()->remove($old);
        $this->Items()->add($new);
        $this->recordHistoryValueChange('Items', 'Getauscht', $old->getTitleWithNumber(), $new->getTitleWithNumber());
        return null;
    }

    /**
     * Ersetzt nicht mehr verfügbare Objekte automatisch durch freie Objekte
     * derselben Gruppe (z.B. vor der Genehmigung, wenn ein reserviertes Kabel
     * inzwischen defekt ist). Gibt die Objekte zurück, für die es keinen Ersatz gab.
     *
     * @return InventoryItem[]
     */
    public function reassignUnavailableItems(): array
    {
        $missing = [];
        foreach ($this->getConflicting('Items') as $item) {
            $alternatives = $this->getAlternativesFor($item);
            if ($alternatives) {
                $this->swapItem($item, $alternatives[0]);
            } else {
                $missing[] = $item;
            }
        }
        return $missing;
    }

    /**
     * Die verleihende Organisation (bei Org-Inventar); null bei privatem Equipment.
     */
    public function getSourceOrganization(): ?Organization
    {
        if ($this->isPrivateLending()) {
            return null;
        }
        $org = $this->LenderOrganizationID ? $this->LenderOrganization() : $this->Organization();
        return $org && $org->exists() ? $org : null;
    }

    /** Für private Zwecke ausgeliehen (ohne Organisation als Kontext) */
    public function isPrivateUse(): bool
    {
        return !$this->OrganizationID;
    }

    public function isPrivateLending(): bool
    {
        return (bool) $this->LenderID;
    }

    public function isViewableBy(Member $member): bool
    {
        if ($this->isRequestedBy($member) || $this->isLentBy($member)) {
            return true;
        }
        foreach ([$this->Organization(), $this->getSourceOrganization()] as $org) {
            if ($org && $org->exists() && $member->isActiveMemberOfOrg($org)) {
                return true;
            }
        }
        return false;
    }

    public function isLentBy(Member $member): bool
    {
        return $this->isPrivateLending() && (int) $this->LenderID === (int) $member->ID;
    }

    public function isRequestedBy(Member $member): bool
    {
        return (int) $this->MemberID === (int) $member->ID;
    }

    /**
     * Private Ausleihen verwaltet nur der Besitzer, Org-Ausleihen wer
     * INVENTORY_APPROVE_RENTALS in der verleihenden Organisation hat.
     */
    public function canBeManagedBy(Member $member): bool
    {
        if ($this->isPrivateLending()) {
            return $this->isLentBy($member);
        }
        $org = $this->getSourceOrganization();
        return $org && $member->hasOrgPermission($org, OrgPermissions::INVENTORY_APPROVE_RENTALS);
    }

    /**
     * Über einen Antrag entscheidet, wer die Ausleihe verwalten darf (siehe
     * canBeManagedBy()) — ausdrücklich auch beim eigenen Antrag, z.B. wenn ein
     * Administrator ein Fahrzeug seiner Organisation privat ausleiht.
     */
    public function canBeDecidedBy(Member $member): bool
    {
        return $this->Status === 'requested' && $this->canBeManagedBy($member);
    }

    public function canBeHandedOverBy(Member $member): bool
    {
        return $this->Status === 'approved' && $this->canBeManagedBy($member);
    }

    public function canBeReturnedBy(Member $member): bool
    {
        return $this->Status === 'handed_over' && $this->canBeManagedBy($member);
    }

    public function canBeCancelledBy(Member $member): bool
    {
        return in_array($this->Status, ['requested', 'approved'], true)
            && ($this->isRequestedBy($member) || $this->canBeManagedBy($member));
    }
}
