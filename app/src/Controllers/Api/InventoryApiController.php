<?php

namespace App\Controllers\Api;

use App\Calendar\Appointment;
use App\Controllers\ApiController;
use App\Inventory\InventoryDamageReport;
use App\Inventory\InventoryItem;
use App\Inventory\InventoryItemType;
use App\Inventory\InventoryTypeField;
use App\Inventory\InventoryRental;
use App\Notifications\PushNotificationService;
use App\Rooms\Room;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\InventoryApiController
 *
 * Inventar-Totem: Objekte, ihre Arten und Ausleihen.
 */
class InventoryApiController extends ApiController
{
    use AttachmentUploads;

    private static $url_segment = 'api/v1/inventory';

    private static $allowed_actions = [
        'index',
        'detail',
        'store',
        'update',
        'remove',
        'uploadFiles',
        'removeFile',
        'duplicate',
        'itemHistory',
        'share',
        'public',
        'typeStore',
        'typeUpdate',
        'typeRemove',
        'rentals',
        'rentalDetail',
        'rentalOptions',
        'rentalStore',
        'rentalDecide',
        'rentalStatus',
        'rentalSwapItem',
        'rentalHistory',
        'damageStore',
        'damageResolve',
    ];


    /**
     * Eigenschaften, die beim Bearbeiten mit ApplyToGroup auf alle gleichen Objekte
     * übertragen werden. Von den Zusatzfeldern (`Values`) nur die nicht als
     * "pro Objekt" markierten, siehe groupValues().
     */
    private const GROUP_FIELDS = ['Title', 'Description', 'TypeID', 'SharedWithIDs', 'PrivateRentable', 'Values'];

    /** @var array<int, array> Zusatzfeld-Änderungen je Objekt (spl_object_id), für den Verlauf nach dem Speichern */
    private array $pendingMetaChanges = [];

    protected function getDefaultAction()
    {
        return 'index';
    }

    // ---------------------------------------------------------------------
    // Formatierung
    // ---------------------------------------------------------------------

    private function formatMember(?Member $member): ?array
    {
        if (!$member || !$member->exists()) {
            return null;
        }
        return [
            'ID'     => $member->ID,
            'Name'   => $member->getDisplayName(),
            'Avatar' => $member->RenderProfileImage(),
        ];
    }

    private function formatOrg(?Organization $org): ?array
    {
        if (!$org || !$org->exists()) {
            return null;
        }
        return [
            'ID'      => $org->ID,
            'Title'   => $org->Title,
            'LogoURL' => $org->RenderLogo(40),
        ];
    }

    private function formatType(InventoryItemType $type, Member $member): array
    {
        $data = [
            'ID'             => $type->ID,
            'Title'          => $type->Title,
            'Description'    => $type->Description,
            'OrganizationID' => (int) $type->OrganizationID,
            'Fields'         => $this->formatTypeFields($type),
            'AppliesTo'      => $type->AppliesTo ?: 'item',
            // Anzahl Objekte bzw. Räume dieser Art
            'ItemCount'      => $type->getUsageCount(),
            'CanEdit'        => $type->isEditableBy($member),
        ];
        return $data;
    }

    private function formatTypeFields(?InventoryItemType $type): array
    {
        return $type && $type->exists() ? $type->getFieldsForApi() : [];
    }

    /** Wählbare Feldformate als [{ value, label, unit, input }] fürs Frontend */
    private function fieldFormats(): array
    {
        $formats = [];
        foreach (InventoryTypeField::FORMATS as $key => $format) {
            $formats[] = ['value' => $key, 'label' => $format['label'], 'unit' => $format['unit'], 'input' => $format['input']];
        }
        return $formats;
    }

    private function formatOwner(InventoryItem $item): array
    {
        if ($item->OwnerType === 'member') {
            $owner = $item->OwnerMember();
            return [
                'Type'   => 'member',
                'Member' => $this->formatMember($owner),
                'Name'   => $owner && $owner->exists() ? $owner->getDisplayName() : 'Unbekannt',
            ];
        }
        $org = $item->Organization();
        return [
            'Type'   => 'organization',
            'Member' => null,
            'Name'   => $org && $org->exists() ? $org->Title : '',
        ];
    }

    /**
     * @param array<int, true>|null $rentedOut vorab ermittelte IDs aktuell übergebener Objekte (Listenansicht)
     */
    private function formatItem(InventoryItem $item, Member $member, ?array $rentedOut = null): array
    {
        $type = $item->Type();
        $firstImage = $item->Images()->first();

        $data = [
            'ID'              => $item->ID,
            'Title'           => $item->Title,
            'Description'     => $item->Description,
            'InventoryNumber' => $item->InventoryNumber,
            'Status'          => $item->Status,
            'StatusLabel'     => InventoryItem::STATUS_LABELS[$item->Status] ?? $item->Status,
            'OwnerType'       => $item->OwnerType,
            'IsPrivate'       => $item->isPrivate(),
            'IsMine'          => $item->isOwnedBy($member),
            'OwnerMemberID'   => (int) $item->OwnerMemberID ?: null,
            'PrivateRentable' => (bool) $item->PrivateRentable,
            'Kind'            => $item->Kind ?: 'item',
            'Mileage'         => (int) $item->Mileage ?: null,
            'CanRentPrivately' => $item->canBeRentedPrivatelyBy($member),
            'Owner'           => $this->formatOwner($item),
            'Organization'    => $item->isPrivate() ? null : $this->formatOrg($item->Organization()),
            'OrganizationID'  => (int) $item->OrganizationID ?: null,
            // Weitere Organisationen, für die das Objekt freigegeben ist
            'SharedWith'      => array_map(fn (Organization $o) => ['ID' => $o->ID, 'Title' => $o->Title], $item->getActiveSharedOrgs()),
            'RentableOrgIDs'  => array_map(fn (Organization $o) => $o->ID, $item->getRentableOrgs()),
            'Type'            => $type && $type->exists() ? ['ID' => $type->ID, 'Title' => $type->Title] : null,
            'TypeID'          => (int) $item->TypeID ?: null,
            'Fields'          => $this->formatTypeFields($type),
            // Werte der Zusatzfelder: { "<Feld-ID>": "<Wert>" }
            'Values'          => (object) $item->getMetaValueMap(),
            'Thumbnail'       => $firstImage && $firstImage->exists() ? $firstImage->Fill(160, 160)->getURL() : null,
            'GroupKey'        => $item->GroupKey,
            'IsRentedOut'     => $rentedOut !== null ? isset($rentedOut[$item->ID]) : $item->getCurrentRental() !== null,
            'CanEdit'         => $item->isEditableBy($member),
            'CanDelete'       => $item->isDeletableBy($member),
        ];

        return $data;
    }

    private function formatItemDetail(InventoryItem $item, Member $member): array
    {
        $data = $this->formatItem($item, $member);

        $data['GroupSize'] = $item->getGroupItems()->count();
        // Alle Schadensmeldungen (auch behobene), neueste zuerst
        $data['Damages'] = array_map(
            fn (InventoryDamageReport $report) => $report->toApi($member),
            $item->DamageReports()->limit(50)->toArray()
        );
        // Öffentlicher Teilen-Link — nur für Bearbeitende sichtbar
        $data['ShareURL'] = $item->ShareToken && $item->isEditableBy($member) ? $this->shareURL($item) : null;

        $data += $this->formatAttachments($item);

        $data['Rentals'] = [];
        $rentals = $item->Rentals()
            ->exclude('Status', ['rejected', 'cancelled'])
            ->sort('StartDate DESC')
            ->limit(20);
        foreach ($rentals as $rental) {
            $summary = $this->formatRentalSummary($rental);
            // Fahrzeuge: Kilometerstand bei Übergabe/Rückgabe dieser Ausleihe
            if ($item->isVehicle()) {
                $summary += array_map(fn ($km) => $km ?: null, $rental->getMileage($item));
            }
            $data['Rentals'][] = $summary;
        }

        return $data;
    }

    /** Kompakte Ausleih-Darstellung (z.B. in der Objekt-Detailansicht) */
    private function formatRentalSummary(InventoryRental $rental): array
    {
        return [
            'ID'          => $rental->ID,
            'StartDate'   => $rental->StartDate,
            'EndDate'     => $rental->EndDate,
            'Status'      => $rental->Status,
            'StatusLabel' => InventoryRental::STATUS_LABELS[$rental->Status] ?? $rental->Status,
            'Member'      => $this->formatMember($rental->Member()),
        ];
    }

    private function formatRental(InventoryRental $rental, Member $member, bool $detailed = false): array
    {
        $items = [];
        foreach ($rental->Items() as $item) {
            $items[] = [
                'ID'              => $item->ID,
                'Title'           => $item->Title,
                'InventoryNumber' => $item->InventoryNumber,
                'Status'          => $item->Status,
                'GroupKey'        => $item->GroupKey,
                'IsVehicle'       => $item->isVehicle(),
                'CurrentMileage'  => (int) $item->Mileage ?: null,
            ] + ($item->isVehicle() ? array_map(fn ($km) => $km ?: null, $rental->getMileage($item)) : []);
        }

        $rooms = [];
        foreach ($rental->Rooms() as $room) {
            $rooms[] = ['ID' => $room->ID, 'Title' => $room->Title];
        }

        $source = $rental->getSourceOrganization();

        $data = [
            'ID'               => $rental->ID,
            'StartDate'        => $rental->StartDate,
            'EndDate'          => $rental->EndDate,
            'Status'           => $rental->Status,
            'StatusLabel'      => InventoryRental::STATUS_LABELS[$rental->Status] ?? $rental->Status,
            'Purpose'          => $rental->Purpose,
            'UsageCondition'   => $rental->UsageCondition,
            'ConditionLabel'   => InventoryRental::CONDITION_LABELS[$rental->UsageCondition] ?? $rental->UsageCondition,
            'ConditionComment' => $rental->ConditionComment,
            'DecisionComment'  => $rental->DecisionComment,
            'DecidedAt'        => $rental->DecidedAt,
            'HandedOverAt'     => $rental->HandedOverAt,
            'ReturnedAt'       => $rental->ReturnedAt,
            'Created'          => $rental->Created,
            'Organization'     => $this->formatOrg($rental->Organization()),
            'OrganizationID'   => (int) $rental->OrganizationID,
            'Member'           => $this->formatMember($rental->Member()),
            'DecidedBy'        => $this->formatMember($rental->DecidedBy()),
            'IsPrivate'        => $rental->isPrivateLending(),
            // Für private Zwecke ausgeliehen (keine Organisation)
            'IsPrivateUse'     => $rental->isPrivateUse(),
            // Verleihende Organisation, wenn sie nicht die Organisation ist, für die ausgeliehen wird
            'LenderOrganization' => $source && (int) $source->ID !== (int) $rental->OrganizationID ? $this->formatOrg($source) : null,
            'Lender'           => $rental->isPrivateLending() ? $this->formatMember($rental->Lender()) : null,
            'IsLentByMe'       => $rental->isLentBy($member),
            'Items'            => $items,
            'Rooms'            => $rooms,
            'IsMine'           => $rental->isRequestedBy($member),
            'CanDecide'        => $rental->canBeDecidedBy($member),
            'CanHandOver'      => $rental->canBeHandedOverBy($member),
            'CanReturn'        => $rental->canBeReturnedBy($member),
            'CanCancel'        => $rental->canBeCancelledBy($member),
            'OpenDamageCount'  => $rental->DamageReports()->filter('ResolvedAt', null)->count(),
            'CanReportDamage'  => InventoryDamageReport::canBeReportedBy($rental, $member),
        ];

        if ($detailed) {
            $data['Damages'] = array_map(
                fn (InventoryDamageReport $report) => $report->toApi($member),
                $rental->DamageReports()->toArray()
            );
            $data['Events'] = [];
            foreach ($rental->Events() as $event) {
                $data['Events'][] = $this->formatEvent($event);
            }

            $checkConflicts = in_array($rental->Status, ['requested', 'approved'], true);
            $data['ConflictingItemIDs'] = $checkConflicts
                ? array_map(fn ($i) => $i->ID, $rental->getConflicting('Items'))
                : [];
            $data['ConflictingRoomIDs'] = $checkConflicts
                ? array_map(fn ($r) => $r->ID, $rental->getConflicting('Rooms'))
                : [];

            // Wer verleiht, kann reservierte Objekte gegen freie gleiche tauschen
            $data['Alternatives'] = [];
            if ($checkConflicts && $rental->canBeManagedBy($member)) {
                foreach ($rental->Items() as $item) {
                    $data['Alternatives'][$item->ID] = array_map(
                        fn (InventoryItem $alt) => ['ID' => $alt->ID, 'InventoryNumber' => $alt->InventoryNumber],
                        $rental->getAlternativesFor($item)
                    );
                }
            }
            $data['Alternatives'] = (object) $data['Alternatives'];
        }

        return $data;
    }

    private function formatEvent(Appointment $event): array
    {
        return [
            'ID'        => $event->ID,
            'Title'     => $event->Title,
            'DateStart' => $event->DateStart,
            'DateEnd'   => $event->DateEnd ?: $event->DateStart,
        ];
    }

    // ---------------------------------------------------------------------
    // Hilfsfunktionen
    // ---------------------------------------------------------------------

    private function findOrg(int $id): ?Organization
    {
        $org = $id ? Organization::get()->byID($id) : null;
        return $org && $org->exists() ? $org : null;
    }

    private function parseDate($value): ?string
    {
        if (!$value || !is_string($value)) {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    /**
     * Übernimmt Stammdaten und die für die Art aktivierten Zusatzfelder aus dem
     * Request-Body. Felder, die die Art nicht (mehr) hat, bleiben unverändert,
     * damit beim Wechsel der Art keine Daten verloren gehen. Der Besitzer wird
     * nicht hier gesetzt (nur beim Anlegen, siehe store()).
     */
    private function applyItemBody(InventoryItem $item, array $body, Member $member): ?string
    {
        if (array_key_exists('Title', $body)) {
            $item->Title = trim((string) $body['Title']);
        }
        if (array_key_exists('Description', $body)) {
            $item->Description = (string) $body['Description'];
        }
        if (array_key_exists('InventoryNumber', $body)) {
            $item->InventoryNumber = trim((string) $body['InventoryNumber']);
        }
        if (array_key_exists('Status', $body)) {
            if (!array_key_exists($body['Status'], InventoryItem::STATUS_LABELS)) {
                return 'Ungültiger Zustand';
            }
            $item->Status = $body['Status'];
        }
        if (array_key_exists('PrivateRentable', $body)) {
            $item->PrivateRentable = (bool) $body['PrivateRentable'];
        }
        if (array_key_exists('Mileage', $body) && $item->isVehicle()) {
            $item->Mileage = max(0, (int) preg_replace('/\D/', '', (string) $body['Mileage']));
        }

        if (array_key_exists('TypeID', $body)) {
            // Org-Objekte nutzen die Arten ihrer Organisation, private Objekte
            // die Arten aller Organisationen des Besitzers
            $typeOrgIDs = $item->isPrivate() ? $this->inventoryOrgIDs($member) : [(int) $item->OrganizationID];
            $type = InventoryItemType::get()->filter([
                'ID'             => (int) $body['TypeID'],
                'OrganizationID' => $typeOrgIDs ?: [0],
                'AppliesTo'      => $item->isVehicle() ? 'vehicle' : 'item',
            ])->first();
            if (!$type) {
                return 'Bitte eine Art auswählen';
            }
            $item->TypeID = $type->ID;
        }

        // Zusatzfelder: { Values: { "<Feld-ID>": "<Wert>" } } — nur Felder der Art des Objekts.
        // Werte von Feldern, die die Art (nach einem Wechsel) nicht hat, bleiben erhalten.
        $type = $item->Type();
        if (isset($body['Values']) && is_array($body['Values']) && $type && $type->exists()) {
            $changes = $item->applyMetaValues($type, $body['Values']);
            if ($changes && $item->isInDB()) {
                $this->pendingMetaChanges[spl_object_id($item)] = $changes;
            }
        }

        return null;
    }

    /**
     * Nur die Zusatzfeld-Werte, die für eine ganze Gruppe gleicher Objekte gelten
     * (nicht als "pro Objekt" markierte Felder der Art).
     */
    private function groupValues(InventoryItem $item, array $values): array
    {
        $type = $item->Type();
        if (!$type || !$type->exists()) {
            return [];
        }
        $shared = $type->Fields()->filter('Individual', false)->column('ID');
        return array_intersect_key($values, array_flip($shared));
    }

    /**
     * Setzt die Freigaben (`SharedWithIDs`) — nur für Organisationen, in denen der
     * Nutzer selbst Mitglied ist (bei Org-Objekten ohne die besitzende). Freigaben
     * für andere Organisationen, die jemand anderes gesetzt hat, bleiben erhalten.
     */
    private function applySharing(InventoryItem $item, array $body, Member $member): void
    {
        if (!array_key_exists('SharedWithIDs', $body)) {
            return;
        }
        $allowed = array_values(array_diff(array_map('intval', $this->inventoryOrgIDs($member)), [(int) $item->OrganizationID]));
        $requested = array_intersect(array_map('intval', (array) $body['SharedWithIDs']), $allowed);
        $foreign = array_diff(array_map('intval', $item->SharedWith()->column('ID')), $allowed);
        $item->SharedWith()->setByIDList(array_values(array_unique([...$requested, ...$foreign])));
    }

    /**
     * Alle Objekte, die der Nutzer sehen darf: Inventar seiner Organisationen,
     * seine eigenen privaten Objekte und private Objekte, die für eine seiner
     * Organisationen freigegeben sind.
     *
     * @return InventoryItem[]
     */
    private function visibleItems(Member $member, array $orgIDs): array
    {
        $items = [];
        $orgItems = InventoryItem::get()
            ->filter('OwnerType', 'organization')
            ->filterAny([
                'OrganizationID' => $orgIDs ?: [0],
                'SharedWith.ID'  => $orgIDs ?: [0],
            ]);
        foreach ($orgItems as $item) {
            if ($item->isViewableBy($member)) {
                $items[] = $item;
            }
        }

        $private = InventoryItem::get()
            ->filter('OwnerType', 'member')
            ->filterAny([
                'OwnerMemberID' => $member->ID,
                'SharedWith.ID' => $orgIDs ?: [0],
            ]);
        foreach ($private as $item) {
            // Freigabe gilt nur, solange auch der Besitzer noch in der Organisation ist
            if ($item->isViewableBy($member)) {
                $items[] = $item;
            }
        }

        usort($items, fn ($a, $b) => strcasecmp((string) $a->Title, (string) $b->Title));
        return $items;
    }

    private function writeWithValidation($record): ?string
    {
        try {
            $record->write();
            // Zusatzfelder stehen als JSON in einer Spalte — für einen lesbaren
            // Verlauf werden ihre Änderungen einzeln protokolliert
            foreach ($this->pendingMetaChanges[spl_object_id($record)] ?? [] as [$field, $old, $new]) {
                $record->recordHistoryValueChange(
                    'Meta' . $field->ID,
                    (string) $field->Label,
                    $field->displayValue($old),
                    $field->displayValue($new)
                );
            }
            unset($this->pendingMetaChanges[spl_object_id($record)]);
            return null;
        } catch (ValidationException $e) {
            $messages = array_map(fn ($m) => $m['message'], $e->getResult()->getMessages());
            return implode(' ', $messages) ?: 'Ungültige Eingabe';
        }
    }

    private function orgsWithPermissions(Member $member): array
    {
        $orgData = [];
        foreach (Organization::get()->filter('ID', $member->getOrganizationIDs() ?: [0])->sort('Title') as $org) {
            if (!$org->EnableInventory) {
                continue;
            }
            $orgData[] = array_merge($this->formatOrg($org), [
                'CanCreate'      => $member->hasOrgPermission($org, OrgPermissions::INVENTORY_CREATE),
                'CanManageTypes' => $member->hasOrgPermission($org, OrgPermissions::INVENTORY_MANAGE_TYPES),
                'CanRequest'     => $member->hasOrgPermission($org, OrgPermissions::INVENTORY_REQUEST_RENTAL),
                'CanApprove'     => $member->hasOrgPermission($org, OrgPermissions::INVENTORY_APPROVE_RENTALS),
            ]);
        }
        return $orgData;
    }

    /** IDs der Organisationen des Nutzers, in denen das Inventar-Totem aktiv ist */
    private function inventoryOrgIDs(Member $member): array
    {
        $orgIDs = $member->getOrganizationIDs();
        if (!$orgIDs) {
            return [];
        }
        return Organization::get()->filter(['ID' => $orgIDs, 'EnableInventory' => true])->column('ID');
    }

    /** Ablageordner für Bilder/Dokumente eines Objekts */
    private function attachmentFolder(InventoryItem $item): string
    {
        $scope = $item->isPrivate()
            ? 'Privat/' . (int) $item->OwnerMemberID
            : $this->attachmentSlug($item->Organization()->exists() ? $item->Organization()->Title : null);
        return 'Inventory/' . $scope . '/' . $this->attachmentSlug($item->InventoryNumber ?: (string) $item->ID);
    }

    // ---------------------------------------------------------------------
    // Objekte
    // ---------------------------------------------------------------------

    /** GET /api/v1/inventory */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = $this->inventoryOrgIDs($member);

        $items = $this->visibleItems($member, $orgIDs);
        $types = InventoryItemType::get()->filter('OrganizationID', $orgIDs ?: [0]);

        // Einmal alle aktuell übergebenen Objekte bestimmen statt pro Objekt abzufragen
        $rentedOut = [];
        $activeRentals = InventoryRental::get()->filter([
            'Status'   => 'handed_over',
            'Items.ID' => array_map(fn (InventoryItem $i) => $i->ID, $items) ?: [0],
        ]);
        foreach ($activeRentals as $rental) {
            foreach ($rental->Items()->column('ID') as $itemID) {
                $rentedOut[(int) $itemID] = true;
            }
        }

        $itemData = [];
        foreach ($items as $item) {
            $itemData[] = $this->formatItem($item, $member, $rentedOut);
        }

        $typeData = [];
        foreach ($types as $type) {
            $typeData[] = $this->formatType($type, $member);
        }

        $statuses = [];
        foreach (InventoryItem::STATUS_LABELS as $value => $label) {
            $statuses[] = ['value' => $value, 'label' => $label];
        }

        $pendingRentals = 0;
        $requested = InventoryRental::get()
            ->filter('Status', 'requested')
            ->filterAny([
                'OrganizationID'       => $orgIDs ?: [0],
                'LenderOrganizationID' => $orgIDs ?: [0],
                'LenderID'             => $member->ID,
            ]);
        foreach ($requested as $rental) {
            if ($rental->canBeDecidedBy($member)) {
                $pendingRentals++;
            }
        }

        return $this->jsonResponse([
            'items'            => $itemData,
            'types'            => $typeData,
            'organizations'    => $this->orgsWithPermissions($member),
            'fieldFormats'     => $this->fieldFormats(),
            'statuses'         => $statuses,
            'pendingRentals'   => $pendingRentals,
        ]);
    }

    /** GET /api/v1/inventory/detail/$ID */
    public function detail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $item = InventoryItem::get()->byID((int) $request->param('ID'));
        if (!$item || !$item->exists()) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }
        if (!$item->isViewableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        return $this->jsonResponse(['item' => $this->formatItemDetail($item, $member)]);
    }

    /** POST /api/v1/inventory/store */
    public function store(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        $item = InventoryItem::create();

        if (($body['OwnerType'] ?? 'organization') === 'member') {
            // Privates Equipment gehört immer der Person, die es einträgt. Sie muss
            // in mindestens einer Organisation mit Inventar sein (für die Arten).
            if (!$this->inventoryOrgIDs($member)) {
                return $this->errorResponse('Du bist in keiner Organisation mit Inventar', 403);
            }
            $item->OwnerType = 'member';
            $item->OwnerMemberID = $member->ID;
        } else {
            $org = $this->findOrg((int) ($body['OrganizationID'] ?? 0));
            if (!$org) {
                return $this->errorResponse('Organisation nicht gefunden', 404);
            }
            if (!$member->hasOrgPermission($org, OrgPermissions::INVENTORY_CREATE)) {
                return $this->errorResponse('Keine Berechtigung, in dieser Organisation Inventar anzulegen', 403);
            }
            $item->OwnerType = 'organization';
            $item->OrganizationID = $org->ID;
        }
        $body += ['TypeID' => 0];
        $item->Kind = ($body['Kind'] ?? 'item') === 'vehicle' ? 'vehicle' : 'item';

        // Mehrere gleiche Objekte auf einmal: jedes bekommt eine eigene Nummer "<Nummer>-01", …
        $count = (int) ($body['Count'] ?? 1);
        if ($count < 1 || $count > 500) {
            return $this->errorResponse('Bitte zwischen 1 und 500 Objekte anlegen', 400);
        }

        if ($count > 1 && isset($body['Values']) && is_array($body['Values'])) {
            // "Pro Objekt"-Felder (Seriennummer, Prüfdatum, …) nicht für alle kopieren;
            // die Art ist hier noch nicht am Objekt gesetzt, daher direkt nachschlagen
            $individual = InventoryTypeField::get()->filter(['TypeID' => (int) $body['TypeID'], 'Individual' => true])->column('ID');
            $body['Values'] = array_diff_key($body['Values'], array_flip($individual));
        }

        if ($error = $this->applyItemBody($item, $body, $member)) {
            return $this->errorResponse($error, 400);
        }
        $numbers = [];
        if ($count > 1) {
            $base = trim((string) $item->InventoryNumber);
            if ($base === '') {
                return $this->errorResponse('Bitte eine Inventarnummer angeben.', 400);
            }
            $ownerID = $item->isPrivate() ? (int) $item->OwnerMemberID : (int) $item->OrganizationID;
            $numbers = InventoryItem::nextNumbers($base, $count, $item->OwnerType, $ownerID);
            $item->InventoryNumber = array_shift($numbers);
        }
        if ($error = $this->writeWithValidation($item)) {
            return $this->errorResponse($error, 400);
        }
        $this->applySharing($item, $body, $member);
        foreach ($numbers as $number) {
            $item->duplicateAs($number);
        }

        return $this->successResponse(
            ['item' => $this->formatItemDetail($item, $member), 'createdCount' => $count],
            $count > 1 ? $count . ' Objekte angelegt' : 'Objekt angelegt'
        );
    }

    /** PUT /api/v1/inventory/update/$ID */
    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $item = InventoryItem::get()->byID((int) $request->param('ID'));
        if (!$item || !$item->exists()) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }
        if (!$item->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        // Vor der Änderung bestimmen — ein neuer Name ändert den GroupKey
        $others = !empty($body['ApplyToGroup'])
            ? $item->getGroupItems()->exclude('ID', $item->ID)->toArray()
            : [];

        if ($error = $this->applyItemBody($item, $body, $member)) {
            return $this->errorResponse($error, 400);
        }
        if ($error = $this->writeWithValidation($item)) {
            return $this->errorResponse($error, 400);
        }
        $this->applySharing($item, $body, $member);

        // Gemeinsame Eigenschaften auf die gleichen Objekte übertragen; Nummer,
        // Zustand, Seriennummer und DGUV-Prüfung bleiben je Objekt individuell
        $common = array_intersect_key($body, array_flip(self::GROUP_FIELDS));
        if (isset($common['Values']) && is_array($common['Values'])) {
            $common['Values'] = $this->groupValues($item, $common['Values']);
        }
        $errors = [];
        foreach ($others as $other) {
            if (!$other->isEditableBy($member)) {
                continue;
            }
            $error = $this->applyItemBody($other, $common, $member) ?: $this->writeWithValidation($other);
            if ($error) {
                $errors[] = $other->InventoryNumber . ': ' . $error;
                continue;
            }
            $this->applySharing($other, $common, $member);
        }

        if ($errors) {
            return $this->jsonResponse([
                'success' => false,
                'error'   => 'Nicht alle Objekte der Gruppe konnten angepasst werden. ' . implode(' ', $errors),
                'data'    => ['item' => $this->formatItemDetail($item, $member)],
            ], 400);
        }

        return $this->successResponse(
            ['item' => $this->formatItemDetail($item, $member)],
            $others ? (count($others) + 1) . ' Objekte gespeichert' : 'Objekt gespeichert'
        );
    }

    /**
     * POST /api/v1/inventory/duplicate/$ID { Count } — weitere gleiche Objekte
     * mit fortlaufenden Nummern anlegen (z.B. 5 weitere Kabel derselben Sorte).
     */
    public function duplicate(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $item = InventoryItem::get()->byID((int) $request->param('ID'));
        if (!$item || !$item->exists()) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }
        // Wer Objekte anlegen darf (Org) bzw. der Besitzer (privat)
        $canCreate = $item->isPrivate()
            ? $item->isOwnedBy($member)
            : $member->hasOrgPermission($item->Organization(), OrgPermissions::INVENTORY_CREATE);
        if (!$canCreate) {
            return $this->errorResponse('Keine Berechtigung, weitere Objekte anzulegen', 403);
        }

        $count = (int) ($this->getJsonBody()['Count'] ?? 1);
        if ($count < 1 || $count > 500) {
            return $this->errorResponse('Bitte zwischen 1 und 500 Objekte anlegen', 400);
        }

        // Nummernbasis: "KAB-07" → "KAB", sonst die Nummer selbst
        $base = preg_replace('/-\d+$/', '', (string) $item->InventoryNumber) ?: (string) $item->InventoryNumber;
        $ownerID = $item->isPrivate() ? (int) $item->OwnerMemberID : (int) $item->OrganizationID;
        $created = [];
        foreach (InventoryItem::nextNumbers($base, $count, $item->OwnerType, $ownerID) as $number) {
            $copy = $item->duplicateAs($number);
            // Ein Duplikat ist neu: Zustand, Kilometerstand und Prüfdaten nicht übernehmen
            $copy->Status = 'available';
            $copy->Mileage = 0;
            $type = $copy->Type();
            if ($type && $type->exists()) {
                $values = $copy->getMetaValueMap();
                foreach ($type->Fields()->filter('Individual', true)->column('ID') as $fieldID) {
                    unset($values[(int) $fieldID]);
                }
                $copy->setMetaValueMap($values);
            }
            $copy->write();
            $created[] = $copy->InventoryNumber;
        }

        return $this->successResponse(
            ['item' => $this->formatItemDetail($item, $member), 'created' => $created],
            $count . ' weitere' . ($count === 1 ? 's Objekt' : ' Objekte') . ' angelegt'
        );
    }

    /** DELETE /api/v1/inventory/remove/$ID */
    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $item = InventoryItem::get()->byID((int) $request->param('ID'));
        if (!$item || !$item->exists()) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }
        if (!$item->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }
        if ($item->Rentals()->filter('Status', InventoryRental::BLOCKING_STATUSES)->exists()) {
            return $this->errorResponse('Das Objekt ist für eine Ausleihe genehmigt oder gerade ausgeliehen und kann nicht gelöscht werden', 400);
        }

        $item->Rentals()->removeAll();
        $item->delete();

        return $this->successResponse([], 'Objekt gelöscht');
    }

    /** POST /api/v1/inventory/uploadFiles/$ID (multipart: images[], documents[]) */
    public function uploadFiles(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $item = InventoryItem::get()->byID((int) $request->param('ID'));
        if (!$item || !$item->exists()) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }
        if (!$item->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        // Mit ApplyToGroup hängen die Dateien an allen gleichen Objekten (einmal gespeichert)
        $targets = !empty($_POST['ApplyToGroup'])
            ? array_filter($item->getGroupItems()->toArray(), fn (InventoryItem $i) => $i->isEditableBy($member))
            : [$item];

        $errors = $this->attachUploadedFiles($targets, $this->attachmentFolder($item));

        if ($errors) {
            return $this->jsonResponse([
                'success' => false,
                'error'   => implode(' ', $errors),
                'data'    => ['item' => $this->formatItemDetail($item, $member)],
            ], 400);
        }

        return $this->successResponse(['item' => $this->formatItemDetail($item, $member)], 'Dateien hochgeladen');
    }

    /** POST /api/v1/inventory/removeFile/$ID { FileID } */
    public function removeFile(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $item = InventoryItem::get()->byID((int) $request->param('ID'));
        if (!$item || !$item->exists()) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }
        if (!$item->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $fileID = (int) ($this->getJsonBody()['FileID'] ?? 0);
        $file = $item->Images()->byID($fileID) ?: $item->Documents()->byID($fileID);
        if (!$file) {
            return $this->errorResponse('Datei nicht gefunden', 404);
        }

        $targets = !empty($this->getJsonBody()['ApplyToGroup'])
            ? array_filter($item->getGroupItems()->toArray(), fn (InventoryItem $i) => $i->isEditableBy($member))
            : [$item];
        foreach ($targets as $target) {
            $target->Images()->removeByID($file->ID);
            $target->Documents()->removeByID($file->ID);
        }
        // Gleiche Objekte teilen sich Dateien — erst löschen, wenn keines sie mehr nutzt
        if (!InventoryItem::isFileShared($file->ID, $item->ID)) {
            $file->deleteFile();
            $file->doArchive();
        }

        return $this->successResponse(['item' => $this->formatItemDetail($item, $member)], 'Datei entfernt');
    }

    private function shareURL(InventoryItem $item): string
    {
        return Director::absoluteURL('/app/inventory/share/' . $item->ShareToken);
    }

    /**
     * POST /api/v1/inventory/share/$ID { Revoke? } — öffentlichen Teilen-Link
     * anlegen (bzw. vorhandenen zurückgeben) oder mit Revoke deaktivieren.
     */
    public function share(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $item = InventoryItem::get()->byID((int) $request->param('ID'));
        if (!$item || !$item->exists()) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }
        if (!$item->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        if (!empty($this->getJsonBody()['Revoke'])) {
            $item->revokeShareToken();
            return $this->successResponse(['url' => null], 'Link deaktiviert');
        }

        $item->ensureShareToken();
        return $this->successResponse(['url' => $this->shareURL($item)], 'Link erstellt');
    }

    /**
     * GET /api/v1/inventory/public/$Token — öffentliche Ansicht über den
     * Teilen-Link, auch ohne Anmeldung. Wer angemeldet ist und das Objekt sehen
     * darf, bekommt die vollständigen Daten (Bearbeiten/Ausleihen im Frontend);
     * sonst nur Stammdaten, Bilder und die als öffentlich markierten Felder.
     */
    public function public(HTTPRequest $request): HTTPResponse
    {
        $token = (string) $request->param('ID');
        $item = $token !== '' ? InventoryItem::get()->filter('ShareToken', $token)->first() : null;
        if (!$item) {
            return $this->errorResponse('Dieser Link ist ungültig oder wurde deaktiviert', 404);
        }

        $member = $this->requireAuth();
        if ($member && $item->isViewableBy($member)) {
            return $this->jsonResponse(['isMember' => true, 'item' => $this->formatItemDetail($item, $member)]);
        }

        $type = $item->Type();
        $values = $item->getMetaValueMap();
        $fields = [];
        foreach ($this->formatTypeFields($type) as $field) {
            if ($field['IsPublic'] && isset($values[$field['ID']])) {
                $field['Value'] = $values[$field['ID']];
                $fields[] = $field;
            }
        }

        // Bei privatem Equipment keinen Namen veröffentlichen
        $owner = $item->isPrivate()
            ? 'Privatbesitz'
            : ($item->Organization()->exists() ? $item->Organization()->Title : '');

        return $this->jsonResponse([
            'isMember' => false,
            'item'     => [
                'Title'           => $item->Title,
                'Description'     => $item->Description,
                'InventoryNumber' => $item->InventoryNumber,
                'Status'          => $item->Status,
                'StatusLabel'     => InventoryItem::STATUS_LABELS[$item->Status] ?? $item->Status,
                'Type'            => $type && $type->exists() ? $type->Title : null,
                'Owner'           => $owner,
                'Fields'          => $fields,
                'Images'          => $this->formatImages($item->Images()),
            ],
        ]);
    }

    /** GET /api/v1/inventory/itemHistory/$ID?before=<EntryID>&limit=20 */
    public function itemHistory(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $item = InventoryItem::get()->byID((int) $request->param('ID'));
        if (!$item || !$item->exists() || !$item->isViewableBy($member)) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }

        return $this->historyResponse($item, $request);
    }

    // ---------------------------------------------------------------------
    // Arten
    // ---------------------------------------------------------------------

    private function applyTypeBody(InventoryItemType $type, array $body): void
    {
        if (array_key_exists('Title', $body)) {
            $type->Title = trim((string) $body['Title']);
        }
        if (array_key_exists('Description', $body)) {
            $type->Description = (string) $body['Description'];
        }
    }

    /**
     * Prüft die Felddefinitionen aus dem Request ({ Fields: [{ ID?, Label, Format, Individual }] }).
     * Gibt einen Fehlertext zurück oder null.
     */
    private function validateTypeFields(array $body): ?string
    {
        if (!array_key_exists('Fields', $body)) {
            return null;
        }
        if (!is_array($body['Fields'])) {
            return 'Ungültige Felder';
        }
        $labels = [];
        foreach ($body['Fields'] as $field) {
            $label = trim((string) ($field['Label'] ?? ''));
            if ($label === '') {
                return 'Bitte jedem Feld eine Bezeichnung geben';
            }
            if (!array_key_exists($field['Format'] ?? '', InventoryTypeField::FORMATS)) {
                return 'Ungültiges Format für "' . $label . '"';
            }
            $key = mb_strtolower($label);
            if (isset($labels[$key])) {
                return 'Das Feld "' . $label . '" gibt es doppelt';
            }
            $labels[$key] = true;
        }
        return null;
    }

    /**
     * Gleicht die Felder der Art mit der Liste aus dem Request ab: vorhandene
     * (per ID) werden aktualisiert, neue angelegt, fehlende gelöscht — samt
     * ihrer Werte an den Objekten der Art. Die Reihenfolge der Liste wird übernommen.
     */
    private function syncTypeFields(InventoryItemType $type, array $body): void
    {
        if (!array_key_exists('Fields', $body)) {
            return;
        }
        $existing = [];
        foreach ($type->Fields() as $field) {
            $existing[$field->ID] = $field;
        }

        $keep = [];
        foreach (array_values($body['Fields']) as $index => $data) {
            $id = (int) ($data['ID'] ?? 0);
            $field = $existing[$id] ?? InventoryTypeField::create();
            $field->TypeID = $type->ID;
            $field->Label = trim((string) $data['Label']);
            $field->Format = $data['Format'];
            $field->Individual = !empty($data['Individual']);
            $field->IsPublic = !empty($data['IsPublic']);
            $field->ShowInList = !empty($data['ShowInList']);
            $field->SortOrder = $index;
            $field->write();
            $keep[$field->ID] = true;
        }

        $removed = array_diff_key($existing, $keep);
        if (!$removed) {
            return;
        }
        foreach ($removed as $field) {
            $field->delete();
        }
        foreach ([...$type->Items()->toArray(), ...$type->Rooms()->toArray()] as $record) {
            $values = array_diff_key($record->getMetaValueMap(), $removed);
            if ($values !== $record->getMetaValueMap()) {
                $record->setMetaValueMap($values);
                $record->write();
            }
        }
    }

    /** POST /api/v1/inventory/typeStore */
    public function typeStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        $org  = $this->findOrg((int) ($body['OrganizationID'] ?? 0));
        if (!$org || !$member->hasOrgPermission($org, OrgPermissions::INVENTORY_MANAGE_TYPES)) {
            return $this->errorResponse('Keine Berechtigung, in dieser Organisation Arten anzulegen', 403);
        }
        if (!trim((string) ($body['Title'] ?? ''))) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        if ($error = $this->validateTypeFields($body)) {
            return $this->errorResponse($error, 400);
        }

        $type = InventoryItemType::create();
        $type->OrganizationID = $org->ID;
        $appliesTo = $body['AppliesTo'] ?? 'item';
        $type->AppliesTo = in_array($appliesTo, ['item', 'vehicle', 'room'], true) ? $appliesTo : 'item';
        $this->applyTypeBody($type, $body);
        $type->write();
        $this->syncTypeFields($type, $body);

        return $this->successResponse(['type' => $this->formatType($type, $member)], 'Art angelegt');
    }

    /** PUT /api/v1/inventory/typeUpdate/$ID */
    public function typeUpdate(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $type = InventoryItemType::get()->byID((int) $request->param('ID'));
        if (!$type || !$type->exists()) {
            return $this->errorResponse('Art nicht gefunden', 404);
        }
        if (!$type->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        if (array_key_exists('Title', $body) && !trim((string) $body['Title'])) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }
        if ($error = $this->validateTypeFields($body)) {
            return $this->errorResponse($error, 400);
        }
        $this->applyTypeBody($type, $body);
        $type->write();
        $this->syncTypeFields($type, $body);

        return $this->successResponse(['type' => $this->formatType($type, $member)], 'Art gespeichert');
    }

    /** DELETE /api/v1/inventory/typeRemove/$ID */
    public function typeRemove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $type = InventoryItemType::get()->byID((int) $request->param('ID'));
        if (!$type || !$type->exists()) {
            return $this->errorResponse('Art nicht gefunden', 404);
        }
        if (!$type->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }
        if ($type->getUsageCount() > 0) {
            return $this->errorResponse($type->isForRooms()
                ? 'Diese Art wird noch von Räumen verwendet und kann nicht gelöscht werden'
                : 'Diese Art wird noch von Objekten verwendet und kann nicht gelöscht werden', 400);
        }

        $type->delete();

        return $this->successResponse([], 'Art gelöscht');
    }

    // ---------------------------------------------------------------------
    // Ausleihen
    // ---------------------------------------------------------------------

    /** GET /api/v1/inventory/rentals */
    public function rentals(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        // Ausleihen im Kontext der eigenen Organisationen, plus eigene Anträge und
        // Ausleihen des eigenen privaten Equipments
        $orgIDs = $this->inventoryOrgIDs($member);
        $rentals = InventoryRental::get()
            ->filterAny([
                'OrganizationID' => $orgIDs ?: [0],
                'LenderID'       => $member->ID,
                // Anfragen anderer Organisationen an freigegebenes eigenes Inventar
                'LenderOrganizationID' => $orgIDs ?: [0],
                'MemberID'       => $member->ID,
            ])
            ->sort('StartDate DESC')
            ->limit(300);

        $data = [];
        foreach ($rentals as $rental) {
            $data[] = $this->formatRental($rental, $member);
        }

        return $this->jsonResponse([
            'rentals'       => $data,
            'organizations' => $this->orgsWithPermissions($member),
        ]);
    }

    /** GET /api/v1/inventory/rentalDetail/$ID */
    public function rentalDetail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $rental = InventoryRental::get()->byID((int) $request->param('ID'));
        if (!$rental || !$rental->exists()) {
            return $this->errorResponse('Ausleihe nicht gefunden', 404);
        }
        if (!$rental->isViewableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        return $this->jsonResponse(['rental' => $this->formatRental($rental, $member, true)]);
    }

    /**
     * GET /api/v1/inventory/rentalOptions?organization=&start=&end=
     * Gruppen gleicher Objekte (eigenes Inventar + für die Organisation
     * freigegebene Objekte anderer Organisationen und Mitglieder) mit der im
     * Zeitraum freien Anzahl (`Available`), reservierbare Räume und Termine der
     * Organisation für das Antragsformular; `busyRoomIDs` sind belegte Räume.
     */
    public function rentalOptions(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        // organization=private: Ausleihe für private Zwecke (ohne Organisation)
        [$ok, $org] = $this->resolveRentalContext($request->getVar('organization'), $member);
        if (!$ok) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        $start = $this->parseDate($request->getVar('start'));
        $end   = $this->parseDate($request->getVar('end')) ?: $start;
        $hasPeriod = $start && $end && $end >= $start;

        // Gleiche Objekte als Gruppe mit Anzahl; ausgeliehen wird eine Anzahl je Gruppe
        $groups = [];
        $rentableItems = $org ? $this->rentableItemsForOrg($org) : $this->privatelyRentableItems($member);
        foreach ($rentableItems as $item) {
            $key = $item->GroupKey;
            if (!isset($groups[$key])) {
                $type = $item->Type();
                $owner = $item->OwnerMember();
                $groups[$key] = [
                    'Key'            => $key,
                    'Title'          => $item->Title,
                    'Type'           => $type && $type->exists() ? $type->Title : null,
                    'IsPrivate'      => $item->isPrivate(),
                    'IsMine'         => $item->isOwnedBy($member),
                    'IsVehicle'      => $item->isVehicle(),
                    'OwnerName'      => $item->isPrivate() && $owner && $owner->exists() ? $owner->getDisplayName() : null,
                    // Besitzende Organisation (für die Gruppierung nach Quelle im Antragsformular)
                    'OrganizationID' => $item->isPrivate() ? null : (int) $item->OrganizationID,
                    'OrgTitle'       => !$item->isPrivate() && $item->Organization()->exists() ? $item->Organization()->Title : null,
                    // Org-Objekt einer anderen Organisation (bzw. bei privater Ausleihe jeder Organisation)
                    'LenderOrgTitle' => !$item->isPrivate() && (int) $item->OrganizationID !== (int) $org?->ID && $item->Organization()->exists()
                        ? $item->Organization()->Title
                        : null,
                    'Count'          => 0,
                    'Capacity'       => 0,
                    'Available'      => 0,
                    'Numbers'        => [],
                    'ItemIDs'        => [],
                ];
            }
            if ($item->Status === 'retired') {
                continue;
            }
            $groups[$key]['Count']++;
            $groups[$key]['Numbers'][] = $item->InventoryNumber;
            $groups[$key]['ItemIDs'][] = $item->ID;
            if ($item->Status === 'available') {
                $groups[$key]['Capacity']++;
                if (!$hasPeriod || !$item->isBookedIn($start, $end)) {
                    $groups[$key]['Available']++;
                }
            }
        }
        $groups = array_values(array_filter($groups, fn ($g) => $g['Count'] > 0));
        foreach ($groups as &$group) {
            sort($group['Numbers'], SORT_NATURAL);
        }
        unset($group);

        // Räume und Termine gehören immer zu einer Organisation — bei privater Ausleihe keine
        $rooms = [];
        if ($org) {
            foreach (Room::get()->filter(['OrganizationID' => $org->ID, 'IsRentable' => true])->sort('Title') as $room) {
                $rooms[] = ['ID' => $room->ID, 'Title' => $room->Title, 'Description' => $room->Description];
            }
        }

        $busyRoomIDs = [];
        $events = [];
        if ($hasPeriod && $org) {
            $roomIDs = array_column($rooms, 'ID');
            foreach (InventoryRental::findConflicts('Rooms', $roomIDs, $start, $end) as $rental) {
                foreach ($rental->Rooms()->column('ID') as $roomID) {
                    $busyRoomIDs[(int) $roomID] = true;
                }
            }

            // Termine ohne Enddatum gelten als eintägig; DateEnd wird deshalb in PHP ausgewertet
            $appointments = Appointment::get()->filter([
                'Organisations.ID'         => $org->ID,
                'DateStart:LessThanOrEqual' => $end,
                'Status:not'                => 'Cancelled',
            ])->sort('DateStart ASC');
            foreach ($appointments as $appointment) {
                if (($appointment->DateEnd ?: $appointment->DateStart) >= $start) {
                    $events[] = $this->formatEvent($appointment);
                }
            }
        }

        return $this->jsonResponse([
            'groups'      => $groups,
            'rooms'       => $rooms,
            'busyRoomIDs' => array_keys($busyRoomIDs),
            'events'      => $events,
        ]);
    }

    /**
     * Objekte, die im Kontext der Organisation ausgeliehen werden können: ihr
     * eigenes Inventar und privates Equipment, das für sie freigegeben ist.
     *
     * @return InventoryItem[]
     */
    private function rentableItemsForOrg(Organization $org): array
    {
        $candidates = InventoryItem::get()->filterAny([
            'OrganizationID' => $org->ID,
            'SharedWith.ID'  => $org->ID,
        ]);
        $items = [];
        foreach ($candidates as $item) {
            // getRentableOrgs() prüft Totem/Mitgliedschaft der Freigaben
            if (in_array($org->ID, array_map(fn (Organization $o) => $o->ID, $item->getRentableOrgs()))) {
                $items[] = $item;
            }
        }
        usort($items, fn ($a, $b) => strcasecmp((string) $a->Title, (string) $b->Title));
        return $items;
    }

    /**
     * Objekte, die $member für private Zwecke ausleihen darf (siehe
     * InventoryItem::canBeRentedPrivatelyBy()).
     *
     * @return InventoryItem[]
     */
    private function privatelyRentableItems(Member $member): array
    {
        return array_values(array_filter(
            $this->visibleItems($member, $this->inventoryOrgIDs($member)),
            fn (InventoryItem $item) => $item->canBeRentedPrivatelyBy($member)
        ));
    }

    /**
     * Kontext einer Ausleihe aus dem Request: "private" = für private Zwecke
     * (keine Organisation), sonst eine Organisation mit Inventar, in der
     * $member Mitglied ist.
     *
     * @return array{0: bool, 1: ?Organization} [gültig, Organisation oder null bei privat]
     */
    private function resolveRentalContext($value, Member $member): array
    {
        if ($value === 'private') {
            return [true, null];
        }
        $org = $this->findOrg((int) $value);
        if (!$org || !$org->EnableInventory || !$member->isActiveMemberOfOrg($org)) {
            return [false, null];
        }
        return [true, $org];
    }

    /**
     * POST /api/v1/inventory/rentalStore
     *
     * Enthält die Auswahl Objekte verschiedener Quellen (Org-Inventar und/oder
     * privates Equipment einzelner Mitglieder), entsteht pro Quelle ein eigener
     * Antrag, über den jeweils unabhängig entschieden wird. Verleiht jemand sein
     * eigenes Equipment, ist dieser Teil sofort genehmigt.
     */
    public function rentalStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        // OrganizationID "private": Ausleihe für private Zwecke (ohne Organisation)
        [$ok, $org] = $this->resolveRentalContext($body['OrganizationID'] ?? null, $member);
        if (!$ok) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        $start = $this->parseDate($body['StartDate'] ?? null);
        $end   = $this->parseDate($body['EndDate'] ?? null) ?: $start;
        if (!$start || !$end) {
            return $this->errorResponse('Bitte einen Zeitraum angeben', 400);
        }
        if ($end < $start) {
            return $this->errorResponse('Das Enddatum liegt vor dem Startdatum', 400);
        }

        // Auswahl: Anzahl je Gruppe gleicher Objekte ({ GroupQuantities: { "<GroupKey>": n } })
        // und/oder konkrete Objekte (ItemIDs). Für Gruppen werden freie Objekte automatisch reserviert.
        $groupQuantities = [];
        foreach ((array) ($body['GroupQuantities'] ?? []) as $key => $quantity) {
            if ((int) $quantity > 0) {
                $groupQuantities[(string) $key] = (int) $quantity;
            }
        }
        $explicitIDs = array_values(array_unique(array_filter(array_map('intval', (array) ($body['ItemIDs'] ?? [])))));
        $roomIDs = array_values(array_unique(array_filter(array_map('intval', (array) ($body['RoomIDs'] ?? [])))));
        if (!$groupQuantities && !$explicitIDs && !$roomIDs) {
            return $this->errorResponse('Bitte mindestens ein Objekt oder einen Raum auswählen', 400);
        }

        if (!$org && $roomIDs) {
            return $this->errorResponse('Räume können nur für eine Organisation reserviert werden', 400);
        }

        $rentable = [];
        foreach ($org ? $this->rentableItemsForOrg($org) : $this->privatelyRentableItems($member) as $item) {
            $rentable[$item->ID] = $item;
        }
        $notRentable = $org
            ? 'Mindestens ein Objekt kann in dieser Organisation nicht ausgeliehen werden'
            : 'Mindestens ein Objekt kann nicht privat ausgeliehen werden';

        $items = [];
        $conflictTitles = [];
        foreach ($explicitIDs as $id) {
            if (!isset($rentable[$id])) {
                return $this->errorResponse($notRentable, 400);
            }
            $item = $rentable[$id];
            if (!$item->isFreeIn($start, $end)) {
                $conflictTitles[] = $item->getTitleWithNumber();
            }
            $items[$id] = $item;
        }
        foreach ($groupQuantities as $key => $quantity) {
            $candidates = array_filter($rentable, fn (InventoryItem $i) => $i->GroupKey === $key && !isset($items[$i->ID]));
            if (!$candidates) {
                return $this->errorResponse($notRentable, 400);
            }
            usort($candidates, fn ($a, $b) => strnatcasecmp((string) $a->InventoryNumber, (string) $b->InventoryNumber));
            $free = array_values(array_filter($candidates, fn (InventoryItem $i) => $i->isFreeIn($start, $end)));
            if (count($free) < $quantity) {
                $conflictTitles[] = $candidates[0]->Title . ' (nur noch ' . count($free) . ' frei)';
                continue;
            }
            foreach (array_slice($free, 0, $quantity) as $item) {
                $items[$item->ID] = $item;
            }
        }
        $items = array_values($items);

        $rooms = Room::get()->filter(['ID' => $roomIDs ?: [0], 'OrganizationID' => $org?->ID ?: 0, 'IsRentable' => true]);
        if ($rooms->count() !== count($roomIDs)) {
            return $this->errorResponse('Mindestens ein Raum gehört nicht zu dieser Organisation oder kann nicht reserviert werden', 400);
        }
        // Nach Quelle aufteilen: "o<ID>" = Inventar einer Organisation (Räume gehören
        // zur Kontext-Organisation), "m<ID>" = privates Equipment eines Mitglieds
        $groups = [];
        foreach ($items as $item) {
            $key = $item->isPrivate() ? 'm' . (int) $item->OwnerMemberID : 'o' . (int) $item->OrganizationID;
            $groups[$key]['items'][] = $item;
        }
        if ($org && $rooms->exists()) {
            $groups['o' . $org->ID]['rooms'] = $rooms->toArray();
        }

        // Wer nur sein eigenes Equipment verleiht, braucht keine Antrags-Berechtigung. Bei
        // privater Ausleihe ist sie schon in privatelyRentableItems() geprüft.
        $needsPermission = array_diff(array_keys($groups), ['m' . (int) $member->ID]);
        if ($org && $needsPermission && !$member->hasOrgPermission($org, OrgPermissions::INVENTORY_REQUEST_RENTAL)) {
            return $this->errorResponse('Keine Berechtigung, in dieser Organisation Ausleihen zu beantragen', 403);
        }

        if ($roomIDs) {
            foreach (InventoryRental::findConflicts('Rooms', $roomIDs, $start, $end) as $other) {
                foreach ($other->Rooms()->filter('ID', $roomIDs) as $room) {
                    $conflictTitles['r' . $room->ID] = $room->Title;
                }
            }
        }
        if ($conflictTitles) {
            return $this->errorResponse('Im gewählten Zeitraum nicht verfügbar: ' . implode(', ', $conflictTitles), 400);
        }

        $eventIDs = array_values(array_unique(array_map('intval', (array) ($body['EventIDs'] ?? []))));
        $events = $eventIDs && $org ? Appointment::get()->filter(['ID' => $eventIDs, 'Organisations.ID' => $org->ID])->toArray() : [];

        $created = [];
        foreach ($groups as $key => $group) {
            $sourceID = (int) substr($key, 1);
            $lenderID = $key[0] === 'm' ? $sourceID : 0;
            $rental = InventoryRental::create();
            $rental->OrganizationID = $org?->ID ?: 0;
            $rental->MemberID = $member->ID;
            $rental->LenderID = $lenderID;
            $rental->LenderOrganizationID = $key[0] === 'o' ? $sourceID : 0;
            $rental->StartDate = $start;
            $rental->EndDate = $end;
            $rental->Purpose = trim((string) ($body['Purpose'] ?? ''));
            $ownEquipment = $lenderID === (int) $member->ID;
            if ($ownEquipment) {
                $rental->Status = 'approved';
                $rental->DecidedByID = $member->ID;
                $rental->DecidedAt = date('Y-m-d H:i:s');
            } else {
                $rental->Status = 'requested';
            }
            $rental->write();

            foreach ($group['items'] ?? [] as $item) {
                $rental->Items()->add($item);
            }
            foreach ($group['rooms'] ?? [] as $room) {
                $rental->Rooms()->add($room);
            }
            foreach ($events as $event) {
                $rental->Events()->add($event);
            }

            if (!$ownEquipment) {
                PushNotificationService::notifyRentalRequested($rental);
            }
            $created[] = $rental;
        }

        // Antrag der eigenen Organisation zuerst, damit `rental` (erstes Element) auf den Hauptantrag zeigt
        $rank = fn (InventoryRental $r) => $r->isPrivateLending() ? 2 : ((int) $r->LenderOrganizationID === (int) $org?->ID ? 0 : 1);
        usort($created, fn ($a, $b) => $rank($a) <=> $rank($b));
        $formatted = array_map(fn (InventoryRental $r) => $this->formatRental($r, $member, true), $created);

        return $this->successResponse([
            'rental'  => $formatted[0],
            'rentals' => $formatted,
        ], count($created) > 1 ? count($created) . ' Anträge erstellt' : 'Ausleihe beantragt');
    }

    /** POST /api/v1/inventory/rentalDecide/$ID { Decision: approve|reject, UsageCondition, ConditionComment, DecisionComment } */
    public function rentalDecide(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $rental = InventoryRental::get()->byID((int) $request->param('ID'));
        if (!$rental || !$rental->exists()) {
            return $this->errorResponse('Ausleihe nicht gefunden', 404);
        }
        if ($rental->Status !== 'requested') {
            return $this->errorResponse('Über diesen Antrag wurde bereits entschieden', 400);
        }
        if (!$rental->canBeDecidedBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $decision = $body['Decision'] ?? '';
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return $this->errorResponse('Ungültige Entscheidung', 400);
        }

        if ($decision === 'approve') {
            $condition = $body['UsageCondition'] ?? 'free';
            if (!array_key_exists($condition, InventoryRental::CONDITION_LABELS)) {
                return $this->errorResponse('Ungültige Auflage', 400);
            }
            $conditionComment = trim((string) ($body['ConditionComment'] ?? ''));
            if ($condition === 'conditional' && !$conditionComment) {
                return $this->errorResponse('Bitte die Bedingung beschreiben', 400);
            }

            // Inzwischen belegte oder defekte Objekte zuerst durch freie gleiche ersetzen
            $conflicts = [...$rental->reassignUnavailableItems(), ...$rental->getConflicting('Rooms')];
            if ($conflicts) {
                return $this->errorResponse('Im Zeitraum bereits anderweitig vergeben: '
                    . implode(', ', array_map(fn ($record) => $record->Title, $conflicts)), 400);
            }

            $rental->Status = 'approved';
            $rental->UsageCondition = $condition;
            $rental->ConditionComment = $condition === 'free' ? '' : $conditionComment;
        } else {
            $rental->Status = 'rejected';
        }

        $rental->DecisionComment = trim((string) ($body['DecisionComment'] ?? ''));
        $rental->DecidedByID = $member->ID;
        $rental->DecidedAt = date('Y-m-d H:i:s');
        $rental->write();

        PushNotificationService::notifyRentalDecision($rental);

        return $this->successResponse(
            ['rental' => $this->formatRental($rental, $member, true)],
            $decision === 'approve' ? 'Ausleihe genehmigt' : 'Ausleihe abgelehnt'
        );
    }

    /** POST /api/v1/inventory/rentalStatus/$ID { Status: handed_over|returned|cancelled } */
    public function rentalStatus(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $rental = InventoryRental::get()->byID((int) $request->param('ID'));
        if (!$rental || !$rental->exists()) {
            return $this->errorResponse('Ausleihe nicht gefunden', 404);
        }

        $body = $this->getJsonBody();
        $status = $body['Status'] ?? '';
        $allowed = match ($status) {
            'handed_over' => $rental->canBeHandedOverBy($member),
            'returned'    => $rental->canBeReturnedBy($member),
            'cancelled'   => $rental->canBeCancelledBy($member),
            default       => false,
        };
        if (!$allowed) {
            return $this->errorResponse('Diese Änderung ist nicht möglich', 403);
        }

        // Optionaler Kilometerstand je Fahrzeug: { Mileages: { "<Objekt-ID>": km } }
        $mileages = [];
        if (in_array($status, ['handed_over', 'returned'], true)) {
            foreach ((array) ($body['Mileages'] ?? []) as $itemID => $value) {
                $km = (int) preg_replace('/\D/', '', (string) $value);
                if ($km > 0) {
                    $mileages[(int) $itemID] = $km;
                }
            }
            foreach ($rental->Items()->filter('ID', array_keys($mileages) ?: [0]) as $vehicle) {
                if (!$vehicle->isVehicle()) {
                    unset($mileages[$vehicle->ID]);
                    continue;
                }
                $start = $rental->getMileage($vehicle)['StartMileage'];
                if ($status === 'returned' && $start && $mileages[$vehicle->ID] < $start) {
                    return $this->errorResponse('Der Kilometerstand bei Rückgabe von "' . $vehicle->Title . '" ist kleiner als bei der Übergabe (' . number_format($start, 0, ',', '.') . ' km)', 400);
                }
            }
        }

        $rental->Status = $status;
        if ($status === 'handed_over') {
            $rental->HandedOverAt = date('Y-m-d H:i:s');
        } elseif ($status === 'returned') {
            $rental->ReturnedAt = date('Y-m-d H:i:s');
        }
        $rental->write();

        foreach ($rental->Items()->filter('ID', array_keys($mileages) ?: [0]) as $vehicle) {
            $km = $mileages[$vehicle->ID];
            $extra = $status === 'handed_over' ? ['StartMileage' => $km] : ['EndMileage' => $km];
            $rental->Items()->add($vehicle, $extra + $rental->getMileage($vehicle));
            // Stand am Fahrzeug fortschreiben (nie zurückdrehen)
            if ($km > (int) $vehicle->Mileage) {
                $vehicle->Mileage = $km;
                $vehicle->write();
            }
        }

        return $this->successResponse(['rental' => $this->formatRental($rental, $member, true)], 'Ausleihe aktualisiert');
    }

    /** POST /api/v1/inventory/rentalSwapItem/$ID { ItemID, NewItemID } — reserviertes Objekt gegen ein gleiches tauschen */
    public function rentalSwapItem(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $rental = InventoryRental::get()->byID((int) $request->param('ID'));
        if (!$rental || !$rental->exists()) {
            return $this->errorResponse('Ausleihe nicht gefunden', 404);
        }
        if (!$rental->canBeManagedBy($member) || !in_array($rental->Status, ['requested', 'approved'], true)) {
            return $this->errorResponse('Diese Änderung ist nicht möglich', 403);
        }

        $body = $this->getJsonBody();
        $old = InventoryItem::get()->byID((int) ($body['ItemID'] ?? 0));
        $new = InventoryItem::get()->byID((int) ($body['NewItemID'] ?? 0));
        if (!$old || !$new) {
            return $this->errorResponse('Objekt nicht gefunden', 404);
        }
        if ($error = $rental->swapItem($old, $new)) {
            return $this->errorResponse($error, 400);
        }

        return $this->successResponse(['rental' => $this->formatRental($rental, $member, true)], 'Objekt getauscht');
    }

    /**
     * POST /api/v1/inventory/damageStore/$ID (Ausleihe; multipart)
     * TargetType (item|room), TargetID, OccurredAt, Description, MakesUnusable, images[] (max. 4)
     */
    public function damageStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $rental = InventoryRental::get()->byID((int) $request->param('ID'));
        if (!$rental || !$rental->exists()) {
            return $this->errorResponse('Ausleihe nicht gefunden', 404);
        }
        if (!InventoryDamageReport::canBeReportedBy($rental, $member)) {
            return $this->errorResponse('Für diese Ausleihe kannst du keinen Schaden melden', 403);
        }

        $targetID = (int) ($_POST['TargetID'] ?? 0);
        $isRoom = ($_POST['TargetType'] ?? 'item') === 'room';
        $target = $isRoom ? $rental->Rooms()->byID($targetID) : $rental->Items()->byID($targetID);
        if (!$target) {
            return $this->errorResponse('Bitte auswählen, was beschädigt ist', 400);
        }

        $description = trim((string) ($_POST['Description'] ?? ''));
        if ($description === '') {
            return $this->errorResponse('Bitte den Schaden beschreiben', 400);
        }
        $timestamp = strtotime((string) ($_POST['OccurredAt'] ?? '')) ?: time();
        if ($timestamp > time() + 300) {
            return $this->errorResponse('Der Zeitpunkt liegt in der Zukunft', 400);
        }

        $images = $this->uploadedFiles('images');
        if (count($images) > InventoryDamageReport::MAX_IMAGES) {
            return $this->errorResponse('Bitte höchstens ' . InventoryDamageReport::MAX_IMAGES . ' Fotos anhängen', 400);
        }

        $report = InventoryDamageReport::create();
        $report->RentalID = $rental->ID;
        $report->ItemID = $isRoom ? 0 : $target->ID;
        $report->RoomID = $isRoom ? $target->ID : 0;
        $report->ReportedByID = $member->ID;
        $report->OccurredAt = date('Y-m-d H:i:s', $timestamp);
        $report->Description = $description;
        $report->MakesUnusable = filter_var($_POST['MakesUnusable'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $report->write();

        $errors = [];
        foreach ($images as $file) {
            $result = $this->storeAttachment($file, 'Damages/' . date('Y') . '/' . $report->ID, Image::class, self::ATTACHMENT_IMAGE_MIMES);
            if ($result['success']) {
                $report->Images()->add($result['file']);
            } else {
                $errors[] = $result['error'];
            }
        }

        $report->applyUnusable();
        $report->write();

        PushNotificationService::notifyDamageReported($report);

        $data = ['rental' => $this->formatRental($rental, $member, true), 'damage' => $report->toApi($member)];
        if ($errors) {
            return $this->jsonResponse(['success' => false, 'error' => 'Schaden gemeldet, aber: ' . implode(' ', $errors), 'data' => $data], 400);
        }
        return $this->successResponse($data, 'Schaden gemeldet');
    }

    /** POST /api/v1/inventory/damageResolve/$ID { ResolvedAt, Note, Restore } */
    public function damageResolve(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $report = InventoryDamageReport::get()->byID((int) $request->param('ID'));
        if (!$report || !$report->exists()) {
            return $this->errorResponse('Schadensmeldung nicht gefunden', 404);
        }
        if (!$report->canBeResolvedBy($member)) {
            return $this->errorResponse('Diesen Schaden kannst du (noch) nicht als behoben markieren', 403);
        }

        $body = $this->getJsonBody();
        $report->ResolvedAt = $this->parseDate($body['ResolvedAt'] ?? null) ?: date('Y-m-d');
        $report->ResolutionNote = trim((string) ($body['Note'] ?? ''));
        $report->ResolvedByID = $member->ID;
        $report->write();

        if (!empty($body['Restore'])) {
            $report->restoreTarget();
        }

        return $this->successResponse(['damage' => $report->toApi($member)], 'Schaden als behoben markiert');
    }

    /** GET /api/v1/inventory/rentalHistory/$ID?before=<EntryID>&limit=20 */
    public function rentalHistory(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $rental = InventoryRental::get()->byID((int) $request->param('ID'));
        if (!$rental || !$rental->exists() || !$rental->isViewableBy($member)) {
            return $this->errorResponse('Ausleihe nicht gefunden', 404);
        }

        return $this->historyResponse($rental, $request);
    }
}
