<?php

namespace App\Inventory;

use App\Rooms\Room;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Member;

/**
 * Class \App\Inventory\StorageLocation
 *
 * Lagerpunkt im Inventar-Totem (Tab "Lager"): ein Ort, Gebäude, eine Kiste oder
 * ein Stellplatz — was genau, legt die Art fest ({@see InventoryItemType} mit
 * `AppliesTo` "storage", inkl. Zusatzfeldern). Lagerpunkte sind verschachtelbar
 * (`Parent`: die Kiste lagert im Haus, das Haus an einem Ort). Objekte und Fahrzeuge
 * ({@see InventoryItem::StorageLocation}) zeigen damit an, wo sie lagern.
 *
 * Jeder Raum hat automatisch einen eigenen Lagerpunkt (`Room` gesetzt, siehe
 * syncForRoom()): so kann man Dinge in einen Raum legen und den Raum selbst in einen
 * Lagerpunkt. Name und Organisation kommen vom Raum; bearbeitet/gelöscht wird er
 * über den Raum, im Lager lässt er sich nur verschieben (mit dem Recht, den Raum zu bearbeiten).
 *
 * Besitz wie bei Objekten: eine Organisation (`OwnerType` "organization") oder
 * privat ein Mitglied ("member"). Sichtbar für Mitglieder der Besitzer-Organisation
 * bzw. den Besitzer, zusätzlich für die Organisationen aus `SharedWith`.
 * Anlegen/Bearbeiten/Löschen regeln die eigenen Rechte STORAGE_* ({@see OrgPermissions}).
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $OwnerType
 * @property ?string $MetaValues
 * @property int $SortOrder
 * @property ?string $ShareToken
 * @property int $OrganizationID
 * @property int $OwnerMemberID
 * @property int $TypeID
 * @property int $ParentID
 * @property int $RoomID
 * @method \App\Rooms\Room Room()
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\Security\Member OwnerMember()
 * @method \App\Inventory\InventoryItemType Type()
 * @method \App\Inventory\StorageLocation Parent()
 * @method \SilverStripe\ORM\DataList|\App\Inventory\StorageLocation[] Children()
 * @method \SilverStripe\ORM\DataList|\App\Inventory\InventoryItem[] Items()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\Organization[] SharedWith()
 */
class StorageLocation extends DataObject
{
    use HasMetaValues;
    use HasShareToken;

    private static $db = [
        "Title"       => "Varchar(255)",
        "Description" => "Text",
        "OwnerType"   => "Enum('organization,member','organization')",
        // Werte der Zusatzfelder der Art als JSON { "<InventoryTypeField-ID>": "<Wert>" }
        "MetaValues"  => "Text",
        // Reihenfolge unter den Lagerpunkten derselben Ebene (Drag & Drop im Lager-Tab)
        "SortOrder"   => "Int",
        // Zufälliger Schlüssel für den öffentlichen Teilen-Link/NFC-Tag (leer = nicht geteilt)
        "ShareToken"  => "Varchar(40)",
    ];

    private static $indexes = [
        "ShareToken" => true,
    ];

    private static $defaults = [
        "OwnerType" => "organization",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
        "OwnerMember"  => Member::class,
        // Art mit AppliesTo = "storage"
        "Type"         => InventoryItemType::class,
        // Übergeordneter Lagerpunkt (0 = oberste Ebene)
        "Parent"       => StorageLocation::class,
        // Gesetzt: Lagerpunkt eines Raums (siehe syncForRoom())
        "Room"         => Room::class,
    ];

    private static $has_many = [
        "Children" => StorageLocation::class . '.Parent',
        "Items"    => InventoryItem::class . '.StorageLocation',
    ];

    private static $many_many = [
        // Weitere Organisationen, deren Mitglieder den Lagerpunkt sehen und nutzen können
        "SharedWith" => Organization::class,
    ];

    private static $default_sort = "SortOrder ASC, Title ASC";

    private static $field_labels = [
        "Title"        => "Name",
        "Description"  => "Beschreibung",
        "OwnerType"    => "Besitzer",
        "OwnerMember"  => "Besitzer (privat)",
        "Organization" => "Organisation",
        "Type"         => "Art",
        "Parent"       => "Lagert in",
        "Children"     => "Enthaltene Lagerpunkte",
        "Items"        => "Objekte",
        "Room"         => "Raum",
        "SharedWith"   => "Freigegeben für",
        "MetaValues"   => "Zusatzfelder",
    ];

    private static $summary_fields = [
        "Title"        => "Name",
        "Type.Title"   => "Art",
        "Parent.Title" => "Lagert in",
        "OwnerLabel"   => "Besitzer",
    ];

    private static $table_name = 'StorageLocation';
    private static $singular_name = "Lagerpunkt";
    private static $plural_name = "Lagerpunkte";

    /** @var array<int, array{0: string, 1: int}>|null ID => [Titel, ParentID] aller Lagerpunkte, für getPathTitles() */
    protected static ?array $pathCache = null;

    /** Beim Verschieben per Drag & Drop setzt der Controller SortOrder selbst (siehe onBeforeWrite()) */
    public bool $keepSortOrder = false;

    public function isPrivate(): bool
    {
        return $this->OwnerType === 'member';
    }

    /** Lagerpunkt eines Raums (Name/Organisation vom Raum, nur verschiebbar) */
    public function isRoom(): bool
    {
        return (bool) $this->RoomID;
    }

    /**
     * Lagerpunkt eines Raums anlegen bzw. Name und Organisation übernehmen
     * (aufgerufen nach jedem Speichern eines Raums).
     */
    public static function syncForRoom(Room $room): self
    {
        $node = self::get()->filter('RoomID', $room->ID)->first() ?? self::create(['RoomID' => $room->ID]);
        if (!$node->isInDB() || $node->Title !== $room->Title || (int) $node->OrganizationID !== (int) $room->OrganizationID) {
            // Schon hier setzen (nicht erst in onBeforeWrite) — validate() läuft vorher
            $node->Title = $room->Title;
            $node->OwnerType = 'organization';
            $node->OrganizationID = $room->OrganizationID;
            $node->write();
        }
        return $node;
    }

    /**
     * Migration: Räume ohne eigenen Lagerpunkt bekommen einen. Lag ein Raum vorher in
     * einem Lagerpunkt (altes Feld Room.StorageLocation), liegt sein Lagerpunkt jetzt dort.
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        foreach (Room::get()->exclude('ID', self::get()->filter('RoomID:GreaterThan', 0)->column('RoomID') ?: [0]) as $room) {
            $node = self::syncForRoom($room);
            if ($room->StorageLocationID && self::get()->byID($room->StorageLocationID)) {
                $node->ParentID = $room->StorageLocationID;
                $node->write();
            }
            if ($room->StorageLocationID) {
                $room->StorageLocationID = 0;
                $room->write();
            }
        }
    }

    public function isOwnedBy(Member $member): bool
    {
        return $this->isPrivate() && (int) $this->OwnerMemberID === (int) $member->ID;
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (!trim((string) $this->Title)) {
            $result->addFieldError('Title', 'Bitte einen Namen angeben.');
        }
        if ($this->isPrivate() && !$this->OwnerMemberID) {
            $result->addFieldError('OwnerMember', 'Privaten Lagerpunkten fehlt der Besitzer.');
        }
        if (!$this->isPrivate() && !$this->OrganizationID) {
            $result->addFieldError('Organization', 'Bitte die Organisation angeben, der der Lagerpunkt gehört.');
        }
        if ($this->ParentID && $this->ID && in_array((int) $this->ParentID, [$this->ID, ...$this->getDescendantIDs()], true)) {
            $result->addFieldError('Parent', 'Ein Lagerpunkt kann nicht in sich selbst oder einem seiner enthaltenen Lagerpunkte lagern.');
        }

        return $result;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        // Lagerpunkt eines Raums: Name und Besitzer immer vom Raum, keine eigene Art/Freigabe
        if ($this->isRoom()) {
            $room = $this->Room();
            if ($room && $room->exists()) {
                $this->Title = $room->Title;
                $this->OwnerType = 'organization';
                $this->OrganizationID = $room->OrganizationID;
                $this->TypeID = 0;
            }
        }
        if ($this->isPrivate()) {
            $this->OrganizationID = 0;
        } else {
            $this->OwnerMemberID = 0;
        }
        // Neu angelegt oder in einen anderen Lagerpunkt verschoben: ans Ende der Ebene
        if (!$this->keepSortOrder && (!$this->isInDB() || $this->isChanged('ParentID'))) {
            $this->SortOrder = (int) self::get()
                ->filter('ParentID', (int) $this->ParentID)
                ->exclude('ID', $this->ID ?: 0)
                ->max('SortOrder') + 1;
        }
        self::$pathCache = null;
    }

    /**
     * Beim Löschen rückt der Inhalt eine Ebene nach oben: enthaltene Lagerpunkte,
     * Objekte und Räume lagern danach im übergeordneten Lagerpunkt (bzw. nirgends).
     */
    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        $parentID = (int) $this->ParentID;
        foreach ([...$this->Children()->toArray(), ...$this->Items()->toArray()] as $record) {
            if ($record instanceof self) {
                $record->ParentID = $parentID;
            } else {
                $record->StorageLocationID = $parentID;
            }
            $record->write();
        }
        self::$pathCache = null;
    }

    /** IDs aller (auch indirekt) enthaltenen Lagerpunkte */
    public function getDescendantIDs(): array
    {
        $ids = [];
        $level = [(int) $this->ID];
        while ($level) {
            $level = array_map('intval', self::get()->filter('ParentID', $level)->column('ID'));
            $level = array_values(array_diff($level, $ids, [(int) $this->ID]));
            $ids = [...$ids, ...$level];
        }
        return $ids;
    }

    /**
     * Titel vom obersten Lagerpunkt bis zu diesem, z.B. ["Vereinsgelände", "Halle", "Kiste 3"].
     * Liest einmal pro Request alle Lagerpunkte (Titel + ParentID) statt pro Ebene abzufragen.
     *
     * @return string[]
     */
    public static function pathTitles(int $id): array
    {
        if (self::$pathCache === null) {
            self::$pathCache = [];
            foreach (SQLSelect::create(['"ID"', '"Title"', '"ParentID"'], '"StorageLocation"')->execute() as $row) {
                self::$pathCache[(int) $row['ID']] = [(string) $row['Title'], (int) $row['ParentID']];
            }
        }
        $titles = [];
        $seen = [];
        while ($id && isset(self::$pathCache[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            [$title, $parentID] = self::$pathCache[$id];
            array_unshift($titles, $title);
            $id = $parentID;
        }
        return $titles;
    }

    /** Kurzform für die API (z.B. am Objekt): { ID, Title, Path } oder null */
    public static function apiSummary(int $id): ?array
    {
        $path = $id ? self::pathTitles($id) : [];
        if (!$path) {
            return null;
        }
        return ['ID' => $id, 'Title' => end($path), 'Path' => $path];
    }

    /**
     * Setzt den Lagerort eines Objekts/Raums (`StorageLocationID`) aus einem Request-Wert
     * (leer/0 = nirgends). Wählbar ist jeder Lagerpunkt, den $member sehen darf — also
     * aus seinen Organisationen, freigegebene und seine eigenen privaten.
     * Gibt einen Fehlertext zurück oder null.
     */
    public static function assignTo(DataObject $record, $value, Member $member): ?string
    {
        $id = (int) $value;
        if ($id === (int) $record->StorageLocationID) {
            return null;
        }
        if ($id) {
            $location = self::get()->byID($id);
            if (!$location || !$location->isViewableBy($member)) {
                return 'Lagerort nicht gefunden';
            }
        }
        $record->StorageLocationID = $id;
        return null;
    }

    public function getOwnerLabel(): string
    {
        if ($this->isPrivate()) {
            $owner = $this->OwnerMember();
            return 'Privat: ' . ($owner && $owner->exists() ? $owner->getDisplayName() : 'Unbekannt');
        }
        $org = $this->Organization();
        return $org && $org->exists() ? (string) $org->Title : '';
    }

    /**
     * Aktive Freigaben: nur Organisationen mit Inventar-Totem; bei privaten
     * Lagerpunkten nur solche, in denen der Besitzer noch Mitglied ist.
     *
     * @return Organization[]
     */
    public function getActiveSharedOrgs(): array
    {
        $shared = $this->SharedWith()->filter('EnableInventory', true)->toArray();
        if (!$this->isPrivate()) {
            return array_values(array_filter($shared, fn (Organization $org) => (int) $org->ID !== (int) $this->OrganizationID));
        }
        $owner = $this->OwnerMember();
        if (!$owner || !$owner->exists()) {
            return [];
        }
        return array_values(array_filter($shared, fn (Organization $org) => $owner->isActiveMemberOfOrg($org)));
    }

    public function isViewableBy(Member $member): bool
    {
        if ($this->isOwnedBy($member)) {
            return true;
        }
        $org = $this->Organization();
        if (!$this->isPrivate() && $org && $org->exists() && $member->isActiveMemberOfOrg($org)) {
            return true;
        }
        foreach ($this->getActiveSharedOrgs() as $shared) {
            if ($member->isActiveMemberOfOrg($shared)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Private Lagerpunkte bearbeitet nur der Besitzer, Org-Lagerpunkte wer STORAGE_EDIT hat.
     * Den Lagerpunkt eines Raums (nur verschieben) darf, wer den Raum bearbeiten darf.
     */
    public function isEditableBy(Member $member): bool
    {
        if ($this->isRoom()) {
            $room = $this->Room();
            return $room && $room->exists() && $room->isEditableBy($member);
        }
        if ($this->isPrivate()) {
            return $this->isOwnedBy($member);
        }
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::STORAGE_EDIT);
    }

    public function isDeletableBy(Member $member): bool
    {
        // Gelöscht wird er mit dem Raum
        if ($this->isRoom()) {
            return false;
        }
        if ($this->isPrivate()) {
            return $this->isOwnedBy($member);
        }
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::STORAGE_DELETE);
    }
}
