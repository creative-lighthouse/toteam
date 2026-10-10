<?php

namespace App\Inventory;

use App\Rooms\Room;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Inventory\InventoryItemType
 *
 * Art eines Inventar-Objekts (z.B. "Scheinwerfer", "Kabel", "Kostüm"), eines
 * Fahrzeugs (z.B. "Transporter"), eines Raums (z.B. "Proberaum") oder eines
 * Lagerpunkts (z.B. "Kiste", "Stellplatz") einer Organisation — siehe `AppliesTo`. Jede Art legt ihre eigenen Zusatzfelder fest
 * ({@see InventoryTypeField}: frei benannt, mit Format wie cm, kg oder Datum).
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $AppliesTo
 * @property int $OrganizationID
 * @method \App\Teams\Organization Organization()
 * @method \SilverStripe\ORM\DataList|\App\Inventory\InventoryItem[] Items()
 * @method \SilverStripe\ORM\DataList|\App\Inventory\InventoryTypeField[] Fields()
 * @method \SilverStripe\ORM\DataList|\App\Rooms\Room[] Rooms()
 * @method \SilverStripe\ORM\DataList|\App\Inventory\StorageLocation[] StorageLocations()
 */
class InventoryItemType extends DataObject
{
    private static $db = [
        "Title"       => "Varchar(255)",
        "Description" => "Text",
        // Ob die Art für Inventar-Objekte, Fahrzeuge, Räume oder Lagerpunkte gilt
        "AppliesTo"   => "Enum('item,vehicle,room,storage','item')",
    ];

    private static $defaults = [
        "AppliesTo" => "item",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
    ];

    private static $has_many = [
        "Items"  => InventoryItem::class . '.Type',
        "Fields" => InventoryTypeField::class . '.Type',
        "Rooms"  => Room::class . '.Type',
        "StorageLocations" => StorageLocation::class . '.Type',
    ];

    private static $cascade_deletes = [
        "Fields",
    ];

    private static $default_sort = "Title ASC";

    private static $field_labels = [
        "Title"        => "Titel",
        "Description"  => "Beschreibung",
        "Organization" => "Organisation",
        "Items"        => "Objekte",
        "Fields"       => "Felder",
        "AppliesTo"    => "Gilt für",
        "Rooms"        => "Räume",
    ];

    private static $summary_fields = [
        "Title"              => "Titel",
        "Organization.Title" => "Organisation",
        "AppliesTo"          => "Gilt für",
    ];

    private static $table_name = 'InventoryItemType';
    private static $singular_name = "Inventar-Art";
    private static $plural_name = "Inventar-Arten";

    /** Die Zusatzfelder fürs Frontend: [{ ID, Label, Format, Unit, Input, Individual, IsPublic, ShowInList }] */
    public function getFieldsForApi(): array
    {
        $fields = [];
        foreach ($this->Fields() as $field) {
            $fields[] = [
                'ID'         => $field->ID,
                'Label'      => $field->Label,
                'Format'     => $field->Format,
                'Unit'       => $field->getUnit(),
                'Input'      => $field->getInput(),
                'Individual' => (bool) $field->Individual,
                'IsPublic'   => (bool) $field->IsPublic,
                'ShowInList' => (bool) $field->ShowInList,
            ];
        }
        return $fields;
    }

    public function isForRooms(): bool
    {
        return $this->AppliesTo === 'room';
    }

    public function isForStorage(): bool
    {
        return $this->AppliesTo === 'storage';
    }

    /** Objekte, Räume bzw. Lagerpunkte dieser Art */
    public function getUsageCount(): int
    {
        if ($this->isForStorage()) {
            return $this->StorageLocations()->count();
        }
        return $this->isForRooms() ? $this->Rooms()->count() : $this->Items()->count();
    }

    public function isEditableBy(Member $member): bool
    {
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::INVENTORY_MANAGE_TYPES);
    }

    public function isDeletableBy(Member $member): bool
    {
        return $this->isEditableBy($member);
    }
}
