<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Maps\Geocoder;
use App\Maps\MapTilesSettings;
use App\Marketing\PosterDistribution;
use App\Marketing\PosterSize;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\MarketingApiController
 *
 */
class MarketingApiController extends ApiController
{
    /** Gruppe für Einträge, deren Ort das Geocoding nicht ermitteln konnte */
    private const UNKNOWN_CITY = 'Ohne Ort';

    private static $url_segment = 'api/v1/marketing';

    private static $allowed_actions = [
        'index',
        'store',
        'update',
        'remove',
        'sizeStore',
        'sizeUpdate',
        'sizeRemove',
        'statistics',
        'reverseGeocode',
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

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

    /**
     * Wandelt einen vom Frontend gesendeten Zeitpunkt (z.B. das
     * `datetime-local`-Format "YYYY-MM-DDTHH:mm") in das von SilverStripe
     * erwartete "Y-m-d H:i:s" um. Liegt kein oder ein ungültiger Wert vor,
     * wird null zurückgegeben (Aufrufer entscheidet dann über den Fallback).
     */
    private function parseDistributedAt(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function formatSize(PosterSize $size): array
    {
        return [
            'ID'             => $size->ID,
            'Title'          => $size->Title,
            'OrganizationID' => (int) $size->OrganizationID,
        ];
    }

    private function formatDistribution(PosterDistribution $distribution, Member $member): array
    {
        $size = $distribution->PosterSize();

        return [
            'ID'                => $distribution->ID,
            'Location'          => $distribution->Location,
            'Quantity'          => $distribution->Quantity,
            'Latitude'          => $distribution->Latitude ?: null,
            'Longitude'         => $distribution->Longitude ?: null,
            // GPS = vom Gerät erfasst, Address = aus dem Ort-Text ermittelt (ungefähr)
            'CoordinatesSource' => $distribution->hasCoordinates() ? $distribution->CoordinatesSource : null,
            'Note'              => $distribution->Note,
            'DistributedAt'     => $distribution->DistributedAt,
            'DistributedAtNice' => $distribution->dbObject('DistributedAt')->Nice(),
            'OrganizationID'    => (int) $distribution->OrganizationID,
            'PosterSize'        => $size && $size->exists() ? $this->formatSize($size) : null,
            'Member'            => $this->formatMember($distribution->Member()),
            'CanEdit'           => $distribution->isEditableBy($member),
            'CanDelete'         => $distribution->isDeletableBy($member),
        ];
    }

    /** GET /api/v1/marketing?organization=&year= */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = $member->getOrganizationIDs();

        $distributions = PosterDistribution::get()->filter('OrganizationID', $orgIDs ?: [0]);
        $sizes         = PosterSize::get()->filter('OrganizationID', $orgIDs ?: [0]);

        $orgID = (int) $request->getVar('organization');
        if ($orgID) {
            $distributions = $distributions->filter('OrganizationID', $orgID);
            $sizes         = $sizes->filter('OrganizationID', $orgID);
        }

        // Years available for the filter dropdown, derived before the year
        // filter itself is applied below.
        $years = [];
        foreach ($distributions->column('DistributedAt') as $distributedAt) {
            if ($distributedAt) {
                $years[(int) substr($distributedAt, 0, 4)] = true;
            }
        }
        $years = array_keys($years);
        rsort($years);

        if ($year = (int) $request->getVar('year')) {
            $distributions = $distributions->filter([
                'DistributedAt:GreaterThanOrEqual' => "$year-01-01 00:00:00",
                'DistributedAt:LessThanOrEqual'    => "$year-12-31 23:59:59",
            ]);
        }

        $data = [];
        foreach ($distributions as $distribution) {
            $data[] = $this->formatDistribution($distribution, $member);
        }

        $sizeData = [];
        foreach ($sizes as $size) {
            $sizeData[] = $this->formatSize($size);
        }

        $orgData = [];
        $canManageSizes = false;
        foreach ($orgIDs as $oid) {
            $org = Organization::get()->byID($oid);
            if ($org) {
                $orgCanManageSizes = $member->hasOrgPermission($org, OrgPermissions::MARKETING_MANAGE_SIZES);
                $orgData[] = [
                    'ID'              => $org->ID,
                    'Title'           => $org->Title,
                    'LogoURL'         => $org->RenderLogo(40),
                    'CanManageSizes'  => $orgCanManageSizes,
                ];
                if ($orgCanManageSizes) {
                    $canManageSizes = true;
                }
            }
        }

        return $this->jsonResponse([
            'distributions'  => $data,
            'sizes'          => $sizeData,
            'organizations'  => $orgData,
            'years'          => $years,
            'canManageSizes' => $canManageSizes,
            // Selbst gehostete Kartendaten für die Karten-Ansicht (null, wenn keine vorhanden)
            'map'            => MapTilesSettings::current()->areaClientConfig(),
        ]);
    }

    /** GET /api/v1/marketing/statistics?organization=&year= */
    public function statistics(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = $member->getOrganizationIDs();
        $distributions = PosterDistribution::get()->filter('OrganizationID', $orgIDs ?: [0]);

        $orgID = (int) $request->getVar('organization');
        if ($orgID) {
            $distributions = $distributions->filter('OrganizationID', $orgID);
        }

        if ($year = (int) $request->getVar('year')) {
            $distributions = $distributions->filter([
                'DistributedAt:GreaterThanOrEqual' => "$year-01-01 00:00:00",
                'DistributedAt:LessThanOrEqual'    => "$year-12-31 23:59:59",
            ]);
        }

        $sizeTitles     = [];   // sizeId => Title
        $sizeTotals     = [];   // sizeId => Quantity
        $memberTotals   = [];   // memberName => [ sizeId => Quantity ]
        $cityTotals     = [];   // Ort => [ sizeId => Quantity ]
        $districtTotals = [];   // "Ort – Stadtteil" => [ sizeId => Quantity ]
        $hasDistricts   = false;

        foreach ($distributions as $distribution) {
            $size     = $distribution->PosterSize();
            $sizeId   = $size && $size->exists() ? $size->ID : 0;
            $sizeTitles[$sizeId] = $size && $size->exists() ? $size->Title : 'Ohne Größe';
            $quantity = $distribution->Quantity;

            $distMember = $distribution->Member();
            $memberName = $distMember && $distMember->exists() ? $distMember->getDisplayName() : 'Unbekannt';

            // Ort und Stadtteil kommen aus dem Geocoding (PosterDistribution::geocode())
            $city = $distribution->City ?: self::UNKNOWN_CITY;
            $district = $distribution->City && $distribution->District ? $city . ' – ' . $distribution->District : $city;
            $hasDistricts = $hasDistricts || $district !== $city;

            $sizeTotals[$sizeId] = ($sizeTotals[$sizeId] ?? 0) + $quantity;
            $memberTotals[$memberName][$sizeId] = ($memberTotals[$memberName][$sizeId] ?? 0) + $quantity;
            $cityTotals[$city][$sizeId] = ($cityTotals[$city][$sizeId] ?? 0) + $quantity;
            $districtTotals[$district][$sizeId] = ($districtTotals[$district][$sizeId] ?? 0) + $quantity;
        }

        arsort($sizeTotals);
        $sizeIds = array_keys($sizeTotals);

        $sizes = [];
        foreach ($sizeIds as $sizeId) {
            $sizes[] = ['ID' => $sizeId, 'Title' => $sizeTitles[$sizeId], 'Total' => $sizeTotals[$sizeId]];
        }

        [$memberLabels, $datasetsBySize] = $this->stackBySize($memberTotals, $sizeIds, $sizeTitles);
        [$cityLabels, $cityDatasets] = $this->stackBySize($cityTotals, $sizeIds, $sizeTitles);
        [$districtLabels, $districtDatasets] = $hasDistricts
            ? $this->stackBySize($districtTotals, $sizeIds, $sizeTitles)
            : [[], []];

        return $this->jsonResponse([
            'sizes'                  => $sizes,
            'memberLabels'           => $memberLabels,
            'datasetsBySize'         => $datasetsBySize,
            'cityLabels'             => $cityLabels,
            'cityDatasetsBySize'     => $cityDatasets,
            // Leer, wenn kein Eintrag einen Stadtteil hat
            'districtLabels'         => $districtLabels,
            'districtDatasetsBySize' => $districtDatasets,
        ]);
    }

    /**
     * Mengen je Gruppe und Größe als gestapelte Chart-Daten: Gruppen absteigend
     * nach Summe ("Ohne Ort" immer zuletzt), ein Datensatz pro Größe.
     *
     * @param array<string, array<int, int>> $totals Gruppe => [ sizeId => Quantity ]
     * @param list<int> $sizeIds
     * @param array<int, string> $sizeTitles
     * @return array{0: list<string>, 1: list<array{sizeId: int, size: string, data: list<int>}>}
     */
    private function stackBySize(array $totals, array $sizeIds, array $sizeTitles): array
    {
        $groupTotals = array_map('array_sum', $totals);
        arsort($groupTotals);
        $labels = array_map('strval', array_keys($groupTotals));
        usort($labels, fn ($a, $b) => ($a === self::UNKNOWN_CITY) <=> ($b === self::UNKNOWN_CITY)
            ?: $groupTotals[$b] <=> $groupTotals[$a]);

        $datasets = [];
        foreach ($sizeIds as $sizeId) {
            $data = [];
            foreach ($labels as $label) {
                $data[] = $totals[$label][$sizeId] ?? 0;
            }
            $datasets[] = ['sizeId' => $sizeId, 'size' => $sizeTitles[$sizeId], 'data' => $data];
        }
        return [$labels, $datasets];
    }

    /**
     * GET /api/v1/marketing/reverseGeocode?lat=&lng=
     *
     * Adresse zur erfassten Position, um das Ort-Feld vorzufüllen. Läuft über
     * den Server, damit der Browser keine Anfragen an Nominatim schickt.
     */
    public function reverseGeocode(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $lat = $request->getVar('lat');
        $lng = $request->getVar('lng');
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return $this->errorResponse('Ungültige Koordinaten', 400);
        }

        try {
            $address = Geocoder::singleton()->reverse((float) $lat, (float) $lng);
        } catch (\Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->warning('Reverse-Geocoding fehlgeschlagen: ' . $e->getMessage());
            return $this->errorResponse('Adresse konnte nicht ermittelt werden', 502);
        }

        return $this->successResponse(['address' => $address]);
    }

    /** POST /api/v1/marketing/store */
    public function store(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body      = $this->getJsonBody();
        $location  = trim($body['Location'] ?? '');
        $latitude  = trim((string) ($body['Latitude'] ?? ''));
        $longitude = trim((string) ($body['Longitude'] ?? ''));
        if (!$location && !($latitude && $longitude)) {
            return $this->errorResponse('Bitte einen Ort eingeben oder die Position erfassen', 400);
        }

        $orgID = (int) ($body['OrganizationID'] ?? 0);
        $org   = $orgID ? Organization::get()->byID($orgID) : null;
        if (!$org || !$org->exists() || !$member->isActiveMemberOfOrg($org)) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        $sizeID = (int) ($body['PosterSizeID'] ?? 0);
        $size   = $sizeID ? PosterSize::get()->filter(['ID' => $sizeID, 'OrganizationID' => $orgID])->first() : null;
        if (!$size) {
            return $this->errorResponse('Ungültige Plakat-Größe', 400);
        }

        $distributedAt = null;
        if (!empty($body['DistributedAt'])) {
            $distributedAt = $this->parseDistributedAt($body['DistributedAt']);
            if (!$distributedAt) {
                return $this->errorResponse('Ungültiger Zeitpunkt', 400);
            }
        }

        try {
            $distribution = PosterDistribution::create();
            $distribution->Location       = $location;
            $distribution->Quantity       = max(1, (int) ($body['Quantity'] ?? 1));
            if ($latitude && $longitude) {
                $distribution->Latitude          = $latitude;
                $distribution->Longitude         = $longitude;
                $distribution->CoordinatesSource = 'GPS';
            }
            $distribution->Note           = $body['Note'] ?? '';
            $distribution->DistributedAt  = $distributedAt;
            $distribution->PosterSizeID   = $size->ID;
            $distribution->OrganizationID = $orgID;
            $distribution->MemberID       = $member->ID;
            $distribution->write();

            return $this->successResponse(['distribution' => $this->formatDistribution($distribution, $member)], 'Eintrag gespeichert');
        } catch (\Exception $e) {
            error_log('MarketingApiController::store error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Speichern des Eintrags', 500);
        }
    }

    /** PUT /api/v1/marketing/update/$ID */
    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $distribution = PosterDistribution::get()->byID((int) $request->param('ID'));
        if (!$distribution || !$distribution->exists()) {
            return $this->errorResponse('Eintrag nicht gefunden', 404);
        }
        if (!$distribution->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();

        if (isset($body['Location'])) {
            $distribution->Location = trim($body['Location']);
        }
        if (isset($body['Quantity'])) {
            $distribution->Quantity = max(1, (int) $body['Quantity']);
        }
        // Das Formular schickt nur GPS-Koordinaten mit. Leere Werte heißen
        // "GPS-Position entfernt" — aus dem Ort ermittelte Koordinaten bleiben
        // davon unberührt und werden bei einer Ortsänderung neu bestimmt.
        if (array_key_exists('Latitude', $body) || array_key_exists('Longitude', $body)) {
            $latitude  = trim((string) ($body['Latitude'] ?? ''));
            $longitude = trim((string) ($body['Longitude'] ?? ''));
            if ($latitude && $longitude) {
                $distribution->Latitude          = $latitude;
                $distribution->Longitude         = $longitude;
                $distribution->CoordinatesSource = 'GPS';
            } elseif ($distribution->CoordinatesSource === 'GPS') {
                $distribution->Latitude          = null;
                $distribution->Longitude         = null;
                $distribution->CoordinatesSource = 'None';
                $distribution->GeocodedLocation  = null;
            }
        }
        if (isset($body['Note'])) {
            $distribution->Note = $body['Note'];
        }
        if (isset($body['DistributedAt'])) {
            $distributedAt = $this->parseDistributedAt($body['DistributedAt']);
            if (!$distributedAt) {
                return $this->errorResponse('Ungültiger Zeitpunkt', 400);
            }
            $distribution->DistributedAt = $distributedAt;
        }
        if (isset($body['PosterSizeID'])) {
            $size = PosterSize::get()->filter(['ID' => (int) $body['PosterSizeID'], 'OrganizationID' => $distribution->OrganizationID])->first();
            if (!$size) {
                return $this->errorResponse('Ungültige Plakat-Größe', 400);
            }
            $distribution->PosterSizeID = $size->ID;
        }

        if (!$distribution->Location && $distribution->CoordinatesSource !== 'GPS') {
            return $this->errorResponse('Bitte einen Ort eingeben oder die Position erfassen', 400);
        }

        $distribution->write();

        return $this->successResponse(['distribution' => $this->formatDistribution($distribution, $member)], 'Eintrag aktualisiert');
    }

    /** DELETE /api/v1/marketing/remove/$ID */
    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $distribution = PosterDistribution::get()->byID((int) $request->param('ID'));
        if (!$distribution || !$distribution->exists()) {
            return $this->errorResponse('Eintrag nicht gefunden', 404);
        }
        if (!$distribution->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $distribution->delete();

        return $this->successResponse([], 'Eintrag gelöscht');
    }

    /** POST /api/v1/marketing/sizeStore */
    public function sizeStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body  = $this->getJsonBody();
        $title = trim($body['Title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $orgID = (int) ($body['OrganizationID'] ?? 0);
        $org   = $orgID ? Organization::get()->byID($orgID) : null;
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::MARKETING_MANAGE_SIZES)) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        $size = PosterSize::create();
        $size->Title          = $title;
        $size->OrganizationID = $orgID;
        $size->SortOrder      = PosterSize::get()->filter('OrganizationID', $orgID)->count();
        $size->write();

        return $this->successResponse(['size' => $this->formatSize($size)], 'Größe erstellt');
    }

    /** PUT /api/v1/marketing/sizeUpdate/$ID */
    public function sizeUpdate(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $size = PosterSize::get()->byID((int) $request->param('ID'));
        if (!$size || !$size->exists()) {
            return $this->errorResponse('Größe nicht gefunden', 404);
        }
        if (!$size->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        if (isset($body['Title'])) {
            $size->Title = trim($body['Title']);
        }
        $size->write();

        return $this->successResponse(['size' => $this->formatSize($size)], 'Größe aktualisiert');
    }

    /** DELETE /api/v1/marketing/sizeRemove/$ID */
    public function sizeRemove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $size = PosterSize::get()->byID((int) $request->param('ID'));
        if (!$size || !$size->exists()) {
            return $this->errorResponse('Größe nicht gefunden', 404);
        }
        if (!$size->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        // Bereits erfasste Verteil-Einträge bleiben erhalten, verlieren aber
        // ihre Größen-Zuordnung, statt beim Löschen einer Größe mitgelöscht zu werden.
        foreach (PosterDistribution::get()->filter('PosterSizeID', $size->ID) as $distribution) {
            $distribution->PosterSizeID = 0;
            $distribution->write();
        }

        $size->delete();

        return $this->successResponse([], 'Größe gelöscht');
    }
}
