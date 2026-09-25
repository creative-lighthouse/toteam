<?php

namespace App\Inventory;

use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Inventory\InventoryItem
 *
 * Ein Inventar-Objekt. Es gehört entweder einer Organisation (`OwnerType`
 * "organization", `Organization` gesetzt) oder privat einem Mitglied
 * (`OwnerType` "member", `OwnerMember` gesetzt, keine Organisation).
 *
 * Über `SharedWith` wird ein Objekt für weitere Organisationen freigegeben:
 * deren Mitglieder sehen es und können es ausleihen. Mit `PrivateRentable`
 * darf es zudem für private Zwecke (ohne Organisation) ausgeliehen werden. Private Objekte sind nur
 * für den Besitzer und seine freigegebenen Organisationen sichtbar; über ihre
 * Ausleihe entscheidet der Besitzer selbst (siehe InventoryRental::Lender). Bei
 * Org-Objekten entscheidet immer die besitzende Organisation.
 *
 * Jedes Objekt ist ein einzelnes physisches Stück mit eigenem Zustand und
 * eigenen Werten für die Zusatzfelder seiner Art (`MetaValues`, siehe
 * {@see InventoryTypeField}). Gleiche Objekte (gleicher Name, gleiche Art,
 * gleicher Besitzer — siehe `GroupKey`) werden in Liste und Ausleihe als Gruppe
 * mit Anzahl zusammengefasst; beim Ausleihen werden automatisch freie Stücke
 * der Gruppe reserviert.
 *
 * Welche Zusatzfelder gepflegt werden, bestimmt die Art ({@see InventoryItemType}).
 *
 * `Status` beschreibt nur den Zustand des Objekts selbst — ob es gerade
 * ausgeliehen ist, ergibt sich aus den zugehörigen {@see InventoryRental}s.
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $InventoryNumber
 * @property ?string $Status
 * @property ?string $OwnerType
 * @property ?string $GroupKey
 * @property ?string $Kind
 * @property int $Mileage
 * @property bool $PrivateRentable
 * @property ?string $ShareToken
 * @property ?string $MetaValues
 * @property int $OrganizationID
 * @property int $TypeID
 * @property int $OwnerMemberID
 * @method \App\Teams\Organization Organization()
 * @method \App\Inventory\InventoryItemType Type()
 * @method \SilverStripe\Security\Member OwnerMember()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Assets\Image[] Images()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Assets\File[] Documents()
 * @method \SilverStripe\ORM\ManyManyList|\App\Teams\Organization[] SharedWith()
 * @method \SilverStripe\ORM\DataList|\App\Inventory\InventoryDamageReport[] DamageReports()
 * @method \SilverStripe\ORM\ManyManyList|\App\Inventory\InventoryRental[] Rentals()
 * @mixin \App\History\HistoryExtension
 */
class InventoryItem extends DataObject
{
    use HasMetaValues;
    use HasShareToken;

    public const STATUS_LABELS = [
        'available' => 'Einsatzbereit',
        'defective' => 'Defekt',
        'in_repair' => 'In Reparatur',
        'retired'   => 'Ausgemustert',
    ];

    private static $db = [
        "Title"           => "Varchar(255)",
        "Description"     => "Text",
        "InventoryNumber" => "Varchar(64)",
        "Status"          => "Enum('available,defective,in_repair,retired','available')",
        "OwnerType"       => "Enum('organization,member','organization')",
        // Gruppierung gleicher Objekte, siehe computeGroupKey()
        "GroupKey"        => "Varchar(255)",
        // "vehicle": Fahrzeug (eigener Tab, Kilometerstand bei Übergabe/Rückgabe)
        "Kind"            => "Enum('item,vehicle','item')",
        // Letzter bekannter Kilometerstand (nur Fahrzeuge, 0 = unbekannt)
        "Mileage"         => "Int",
        // Darf (zusätzlich zu Organisationen) auch für private Zwecke ausgeliehen werden
        "PrivateRentable" => "Boolean",
        // Zufälliger Schlüssel für den öffentlichen Teilen-Link (leer = nicht geteilt)
        "ShareToken"      => "Varchar(40)",

        // Werte der Zusatzfelder der Art als JSON { "<InventoryTypeField-ID>": "<Wert>" }
        "MetaValues"      => "Text",
    ];

    private static $defaults = [
        "Status"    => "available",
        "OwnerType" => "organization",
        "Kind"      => "item",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
        "Type"         => InventoryItemType::class,
        "OwnerMember"  => Member::class,
    ];

    private static $has_many = [
        "DamageReports" => InventoryDamageReport::class . '.Item',
    ];

    private static $cascade_deletes = [
        "DamageReports",
    ];

    private static $many_many = [
        "Images"     => Image::class,
        "Documents"  => File::class,
        // Weitere Organisationen, deren Mitglieder das Objekt sehen und ausleihen können
        "SharedWith" => Organization::class,
    ];

    private static $belongs_many_many = [
        "Rentals" => InventoryRental::class . '.Items',
    ];

    private static $owns = [
        "Images",
        "Documents",
    ];

    private static $indexes = [
        "InventoryNumber" => true,
        "GroupKey"        => true,
        "ShareToken"      => true,
    ];

    private static $default_sort = "Title ASC";

    private static $field_labels = [
        "Title"           => "Name",
        "Description"     => "Beschreibung",
        "InventoryNumber" => "Inventarnummer",
        "Status"          => "Zustand",
        "OwnerType"       => "Besitzer",
        "OwnerMember"     => "Besitzer (privat)",
        "SharedWith"      => "Freigegeben für",
        "PrivateRentable" => "Privat ausleihbar",
        "Kind"            => "Objekt/Fahrzeug",
        "Mileage"         => "Kilometerstand",
        "Organization"    => "Organisation",
        "Type"            => "Art",
        "MetaValues"      => "Zusatzfelder",
        "Images"          => "Bilder",
        "Documents"       => "Dokumente",
        "Rentals"         => "Ausleihen",
    ];

    private static $summary_fields = [
        "InventoryNumber"    => "Inventarnummer",
        "Title"              => "Name",
        "Type.Title"         => "Art",
        "OwnerLabel"         => "Besitzer",
        "Status"             => "Zustand",
    ];

    private static $searchable_fields = [
        "Title",
        "InventoryNumber",
    ];

    /**
     * Felder, deren Änderungen im Verlauf erscheinen (siehe HistoryExtension).
     */
    private static $history_fields = [
        'Title',
        'Description',
        'InventoryNumber',
        'Status',
        'OwnerType',
        'OwnerMember',
        'PrivateRentable',
        'Mileage',
        'Type',
    ];

    private static $history_value_labels = [
        'Status'    => self::STATUS_LABELS,
        'OwnerType' => ['organization' => 'Organisation', 'member' => 'Privat'],
    ];

    private static $table_name = 'InventoryItem';
    private static $singular_name = "Inventar-Objekt";
    private static $plural_name = "Inventar-Objekte";

    public function isVehicle(): bool
    {
        return $this->Kind === 'vehicle';
    }

    public function isPrivate(): bool
    {
        return $this->OwnerType === 'member';
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (!trim((string) $this->Title)) {
            $result->addFieldError('Title', 'Bitte einen Namen angeben.');
        }

        if ($this->isPrivate() && !$this->OwnerMemberID) {
            $result->addFieldError('OwnerMember', 'Privaten Objekten fehlt der Besitzer.');
        }
        if (!$this->isPrivate() && !$this->OrganizationID) {
            $result->addFieldError('Organization', 'Bitte die Organisation angeben, der das Objekt gehört.');
        }

        // Eindeutig innerhalb der Organisation bzw. innerhalb der privaten Objekte des Besitzers
        $number = trim((string) $this->InventoryNumber);
        $scope = $this->isPrivate()
            ? ['OwnerType' => 'member', 'OwnerMemberID' => $this->OwnerMemberID]
            : ['OwnerType' => 'organization', 'OrganizationID' => $this->OrganizationID];
        if ($number === '') {
            $result->addFieldError('InventoryNumber', 'Bitte eine Inventarnummer angeben.');
        } elseif (self::get()->filter($scope + ['InventoryNumber' => $number])->exclude('ID', $this->ID ?: 0)->exists()) {
            $result->addFieldError('InventoryNumber', $this->isPrivate()
                ? 'Diese Inventarnummer hast du bereits für ein anderes privates Objekt vergeben.'
                : 'Diese Inventarnummer ist in der Organisation bereits vergeben.');
        }

        return $result;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->InventoryNumber = trim((string) $this->InventoryNumber);
        if ($this->isPrivate()) {
            $this->OrganizationID = 0;
        } else {
            $this->OwnerMemberID = 0;
        }
        $this->GroupKey = $this->computeGroupKey();
    }

    /**
     * Migrationen: Früher hing an "Mitglied"-Objekten zusätzlich die Organisation,
     * der das Mitglied sie zur Verfügung gestellt hat — diese wird zur Freigabe.
     * Objekte ohne GroupKey (vor Einführung der Gruppierung angelegt) bekommen einen.
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $legacy = self::get()->filter(['OwnerType' => 'member', 'OrganizationID:GreaterThan' => 0]);
        foreach ($legacy as $item) {
            $item->SharedWith()->add($item->OrganizationID);
            $item->OrganizationID = 0;
            $item->write();
        }

        foreach (self::get()->filter('GroupKey', [null, '']) as $item) {
            $item->write();
        }
    }

    public function getTitleWithNumber(): string
    {
        return $this->InventoryNumber ? $this->InventoryNumber . ' · ' . $this->Title : (string) $this->Title;
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
     * Objekten zusätzlich nur solche, in denen der Besitzer noch Mitglied ist
     * (tritt er aus, endet die Freigabe automatisch).
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

    /**
     * Die Organisationen, in deren Kontext das Objekt ausgeliehen werden kann:
     * die Besitzer-Organisation plus Freigaben bzw. bei privaten Objekten die aktiven Freigaben.
     *
     * @return Organization[]
     */
    public function getRentableOrgs(): array
    {
        if ($this->isPrivate()) {
            return $this->getActiveSharedOrgs();
        }
        $org = $this->Organization();
        if (!$org || !$org->exists()) {
            return [];
        }
        return [$org, ...$this->getActiveSharedOrgs()];
    }

    // ---- Gruppierung & Verfügbarkeit ----

    /**
     * Gleiche Objekte: gleicher Besitzer, gleiche Art und gleicher Name
     * (ohne Groß-/Kleinschreibung und doppelte Leerzeichen).
     */
    public function computeGroupKey(): string
    {
        $owner = $this->isPrivate() ? 'm' . (int) $this->OwnerMemberID : 'o' . (int) $this->OrganizationID;
        $title = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $this->Title)));
        // Fahrzeuge nie mit gleichnamigen Objekten zusammenfassen
        $kind = $this->isVehicle() ? '|v' : '';
        return $owner . '|t' . (int) $this->TypeID . $kind . '|' . $title;
    }

    /** Alle Objekte der Gruppe (inkl. diesem) */
    public function getGroupItems(): DataList
    {
        return self::get()->filter('GroupKey', $this->GroupKey ?: $this->computeGroupKey())->sort('InventoryNumber ASC');
    }

    /** Ob das Objekt im Zeitraum durch eine genehmigte/übergebene Ausleihe belegt ist */
    public function isBookedIn(string $start, string $end, int $excludeRentalID = 0): bool
    {
        return InventoryRental::findConflicts('Items', [$this->ID], $start, $end, $excludeRentalID)->exists();
    }

    /** Einsatzbereit und im Zeitraum nicht belegt */
    public function isFreeIn(string $start, string $end, int $excludeRentalID = 0): bool
    {
        return $this->Status === 'available' && !$this->isBookedIn($start, $end, $excludeRentalID);
    }

    /**
     * Nächste freie Inventarnummern "<Basis>-01", "<Basis>-02", … im
     * Nummernkreis des Besitzers (Organisation bzw. privates Equipment).
     *
     * @return string[]
     */
    public static function nextNumbers(string $base, int $count, string $ownerType, int $ownerID): array
    {
        $scope = $ownerType === 'member'
            ? ['OwnerType' => 'member', 'OwnerMemberID' => $ownerID]
            : ['OwnerType' => 'organization', 'OrganizationID' => $ownerID];
        $taken = array_flip(self::get()->filter($scope + ['InventoryNumber:StartsWith' => $base])->column('InventoryNumber'));

        $width = max(2, strlen((string) $count));
        $numbers = [];
        $next = 1;
        while (count($numbers) < $count) {
            $number = $base . '-' . str_pad((string) $next++, $width, '0', STR_PAD_LEFT);
            if (!isset($taken[$number])) {
                $numbers[] = $number;
            }
        }
        return $numbers;
    }

    /**
     * Legt eine Kopie mit neuer Inventarnummer an: gleiche Eigenschaften,
     * Freigaben, Bilder und Dokumente (die Dateien werden geteilt, nicht kopiert).
     */
    public function duplicateAs(string $inventoryNumber): self
    {
        $copy = self::create();
        foreach (array_keys(static::config()->get('db')) as $field) {
            $copy->$field = $this->$field;
        }
        $copy->OrganizationID = $this->OrganizationID;
        $copy->TypeID = $this->TypeID;
        $copy->OwnerMemberID = $this->OwnerMemberID;
        $copy->InventoryNumber = $inventoryNumber;
        // Jedes Objekt bekommt ggf. einen eigenen Teilen-Link
        $copy->ShareToken = null;
        $copy->write();

        $copy->SharedWith()->setByIDList($this->SharedWith()->column('ID'));
        $copy->Images()->setByIDList($this->Images()->column('ID'));
        $copy->Documents()->setByIDList($this->Documents()->column('ID'));

        return $copy;
    }

    /**
     * Ob $member das Objekt für private Zwecke ausleihen darf: eigenes privates
     * Equipment immer (unabhängig von PrivateRentable — das regelt nur, ob
     * *andere* es privat ausleihen dürfen). Sonst muss das Objekt privat
     * ausleihbar sein und $member in einer der Organisationen, über die er es
     * sieht, Ausleihen beantragen dürfen.
     */
    public function canBeRentedPrivatelyBy(Member $member): bool
    {
        if ($this->isOwnedBy($member)) {
            return true;
        }
        if (!$this->PrivateRentable) {
            return false;
        }
        foreach ($this->getRentableOrgs() as $org) {
            if ($member->hasOrgPermission($org, OrgPermissions::INVENTORY_REQUEST_RENTAL)) {
                return true;
            }
        }
        return false;
    }

    public function isViewableBy(Member $member): bool
    {
        if ($this->isOwnedBy($member)) {
            return true;
        }
        foreach ($this->getRentableOrgs() as $org) {
            if ($member->isActiveMemberOfOrg($org)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Private Objekte bearbeitet nur der Besitzer, Org-Objekte wer INVENTORY_EDIT hat.
     */
    public function isEditableBy(Member $member): bool
    {
        if ($this->isPrivate()) {
            return $this->isOwnedBy($member);
        }
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::INVENTORY_EDIT);
    }

    public function isDeletableBy(Member $member): bool
    {
        if ($this->isPrivate()) {
            return $this->isOwnedBy($member);
        }
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::INVENTORY_DELETE);
    }

    public function isOwnedBy(Member $member): bool
    {
        return $this->isPrivate() && (int) $this->OwnerMemberID === (int) $member->ID;
    }

    /**
     * Die Ausleihe, bei der das Objekt gerade übergeben (also unterwegs) ist.
     */
    public function getCurrentRental(): ?InventoryRental
    {
        return $this->Rentals()->filter('Status', 'handed_over')->first();
    }

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();

        // Gleiche Objekte teilen sich Bilder/Dokumente (siehe duplicateAs()) — nur
        // löschen, was kein anderes Objekt mehr verwendet. Dateien sind versioniert:
        // doArchive() entfernt sie aus Entwurf und Live (delete() träfe nur eine Stufe)
        foreach (['Images', 'Documents'] as $relation) {
            foreach ($this->$relation() as $file) {
                $usedElsewhere = self::get()
                    ->filter($relation . '.ID', $file->ID)
                    ->exclude('ID', $this->ID)
                    ->exists();
                if (!$usedElsewhere) {
                    $file->deleteFile();
                    $file->doArchive();
                }
            }
        }
    }

    /** Ob die Datei noch an einem anderen Objekt hängt (z.B. an einem gleichen Objekt der Gruppe) */
    public static function isFileShared(int $fileID, int $exceptItemID): bool
    {
        return self::get()->filterAny(['Images.ID' => $fileID, 'Documents.ID' => $fileID])->exclude('ID', $exceptItemID)->exists();
    }
}
