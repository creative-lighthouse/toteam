<?php

namespace App\Inventory;

use App\Rooms\Room;
use App\Teams\OrgPermissions;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Inventory\InventoryDamageReport
 *
 * Ein während (oder bei der Rückgabe) einer Ausleihe gemeldeter Schaden an einem
 * Objekt/Fahrzeug oder Raum dieser Ausleihe — mit Zeitpunkt, Beschreibung und bis
 * zu vier Fotos. Was danach passiert, klären die Beteiligten außerhalb von ToTeam;
 * der Verleiher kann den Schaden später als behoben markieren.
 *
 * `MakesUnusable` setzt beim Melden das Objekt auf "defekt" bzw. den Raum auf
 * "nicht reservierbar". `ChangedTarget` merkt sich, ob diese Meldung das
 * tatsächlich geändert hat — nur dann wird beim Beheben der alte Zustand
 * wiederhergestellt (sofern gewünscht und kein anderer offener Schaden das
 * Objekt weiterhin unbenutzbar macht).
 *
 * @property ?string $OccurredAt
 * @property ?string $Description
 * @property bool $MakesUnusable
 * @property bool $ChangedTarget
 * @property ?string $ResolvedAt
 * @property ?string $ResolutionNote
 * @property int $RentalID
 * @property int $ItemID
 * @property int $RoomID
 * @property int $ReportedByID
 * @property int $ResolvedByID
 * @method \App\Inventory\InventoryRental Rental()
 * @method \App\Inventory\InventoryItem Item()
 * @method \App\Rooms\Room Room()
 * @method \SilverStripe\Security\Member ReportedBy()
 * @method \SilverStripe\Security\Member ResolvedBy()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Assets\Image[] Images()
 */
class InventoryDamageReport extends DataObject
{
    public const MAX_IMAGES = 4;

    private static $db = [
        "OccurredAt"     => "Datetime",
        "Description"    => "Text",
        "MakesUnusable"  => "Boolean",
        "ChangedTarget"  => "Boolean",
        "ResolvedAt"     => "Date",
        "ResolutionNote" => "Text",
    ];

    private static $has_one = [
        "Rental"     => InventoryRental::class,
        "Item"       => InventoryItem::class,
        "Room"       => Room::class,
        "ReportedBy" => Member::class,
        "ResolvedBy" => Member::class,
    ];

    private static $many_many = [
        "Images" => Image::class,
    ];

    private static $owns = [
        "Images",
    ];

    private static $default_sort = "OccurredAt DESC";

    private static $field_labels = [
        "OccurredAt"     => "Zeitpunkt",
        "Description"    => "Beschreibung",
        "MakesUnusable"  => "Macht unbenutzbar",
        "ResolvedAt"     => "Behoben am",
        "ResolutionNote" => "Notiz zur Behebung",
        "Rental"         => "Ausleihe",
        "Item"           => "Objekt",
        "Room"           => "Raum",
        "ReportedBy"     => "Gemeldet von",
        "ResolvedBy"     => "Behoben von",
        "Images"         => "Fotos",
    ];

    private static $summary_fields = [
        "OccurredAt"      => "Zeitpunkt",
        "TargetTitle"     => "Objekt/Raum",
        "ReportedBy.Name" => "Gemeldet von",
        "ResolvedAt"      => "Behoben am",
    ];

    private static $table_name = 'InventoryDamageReport';
    private static $singular_name = "Schadensmeldung";
    private static $plural_name = "Schadensmeldungen";

    public function isOpen(): bool
    {
        return !$this->ResolvedAt;
    }

    /** Das betroffene Objekt bzw. der betroffene Raum */
    public function getTarget(): ?DataObject
    {
        if ($this->ItemID && $this->Item()->exists()) {
            return $this->Item();
        }
        if ($this->RoomID && $this->Room()->exists()) {
            return $this->Room();
        }
        return null;
    }

    public function getTargetTitle(): string
    {
        $target = $this->getTarget();
        if ($target instanceof InventoryItem) {
            return $target->getTitleWithNumber();
        }
        return $target ? (string) $target->Title : '';
    }

    /**
     * Melden darf während der Mietzeit die ausleihende Seite (antragstellende
     * Person oder Mitglieder der Organisation, für die ausgeliehen wurde, mit
     * Ausleihrecht) sowie der Verleiher; nach der Rückgabe nur noch der Verleiher.
     */
    public static function canBeReportedBy(InventoryRental $rental, Member $member): bool
    {
        if ($rental->Status === 'handed_over') {
            if ($rental->isRequestedBy($member) || $rental->canBeManagedBy($member)) {
                return true;
            }
            $org = $rental->Organization();
            return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::INVENTORY_REQUEST_RENTAL);
        }
        return $rental->Status === 'returned' && $rental->canBeManagedBy($member);
    }

    /** Als behoben markieren darf der Verleiher, sobald die Mietzeit vorbei ist */
    public function canBeResolvedBy(Member $member): bool
    {
        $rental = $this->Rental();
        return $this->isOpen()
            && $rental->exists()
            && $rental->Status !== 'handed_over'
            && $rental->canBeManagedBy($member);
    }

    /** Beim Melden: Objekt auf "defekt" bzw. Raum auf "nicht reservierbar" setzen */
    public function applyUnusable(): void
    {
        if (!$this->MakesUnusable) {
            return;
        }
        $target = $this->getTarget();
        if ($target instanceof InventoryItem && $target->Status === 'available') {
            $target->Status = 'defective';
            $target->write();
            $this->ChangedTarget = true;
        } elseif ($target instanceof Room && $target->IsRentable) {
            $target->IsRentable = false;
            $target->write();
            $this->ChangedTarget = true;
        }
    }

    /**
     * Beim Beheben: vorherigen Zustand wiederherstellen — nur wenn diese Meldung ihn
     * geändert hat und kein anderer offener Schaden das Objekt unbenutzbar macht.
     */
    public function restoreTarget(): void
    {
        if (!$this->ChangedTarget) {
            return;
        }
        $target = $this->getTarget();
        if (!$target) {
            return;
        }
        $otherOpen = self::get()
            ->filter([
                'MakesUnusable' => true,
                'ResolvedAt'    => null,
                $target instanceof Room ? 'RoomID' : 'ItemID' => $target->ID,
            ])
            ->exclude('ID', $this->ID)
            ->exists();
        if ($otherOpen) {
            return;
        }
        if ($target instanceof InventoryItem && $target->Status === 'defective') {
            $target->Status = 'available';
            $target->write();
        } elseif ($target instanceof Room && !$target->IsRentable) {
            $target->IsRentable = true;
            $target->write();
        }
    }

    /** Darstellung fürs Frontend (Ausleihe, Objekt- und Raum-Detail) */
    public function toApi(Member $member): array
    {
        $person = fn (Member $m) => $m->exists() ? ['ID' => $m->ID, 'Name' => $m->getDisplayName(), 'Avatar' => $m->RenderProfileImage()] : null;
        $images = [];
        foreach ($this->Images() as $image) {
            $images[] = [
                'ID'        => $image->ID,
                'URL'       => $image->getURL(),
                'Thumbnail' => $image->Fill(200, 200)->getURL(),
                'Name'      => $image->Name,
            ];
        }
        $rental = $this->Rental();

        return [
            'ID'             => $this->ID,
            'OccurredAt'     => $this->OccurredAt,
            'Description'    => $this->Description,
            'MakesUnusable'  => (bool) $this->MakesUnusable,
            'ChangedTarget'  => (bool) $this->ChangedTarget,
            'ResolvedAt'     => $this->ResolvedAt,
            'ResolutionNote' => $this->ResolutionNote,
            'IsOpen'         => $this->isOpen(),
            'TargetType'     => $this->RoomID ? 'room' : 'item',
            'TargetID'       => (int) ($this->RoomID ?: $this->ItemID),
            'TargetTitle'    => $this->getTargetTitle(),
            'RentalID'       => (int) $this->RentalID,
            'RentalMember'   => $rental->exists() ? $person($rental->Member()) : null,
            'ReportedBy'     => $person($this->ReportedBy()),
            'ResolvedBy'     => $person($this->ResolvedBy()),
            'Images'         => $images,
            'CanResolve'     => $this->canBeResolvedBy($member),
        ];
    }

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        // Dateien sind versioniert: doArchive() entfernt sie aus Entwurf und Live
        foreach ($this->Images() as $image) {
            $image->deleteFile();
            $image->doArchive();
        }
    }
}
