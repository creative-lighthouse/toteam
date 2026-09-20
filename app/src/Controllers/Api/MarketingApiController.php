<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Marketing\PosterDistribution;
use App\Marketing\PosterSize;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\MarketingApiController
 *
 */
class MarketingApiController extends ApiController
{
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

        $sizeTitles   = [];   // sizeId => Title
        $sizeTotals   = [];   // sizeId => Quantity
        $memberTotals = [];   // memberName => [ sizeId => Quantity ]

        foreach ($distributions as $distribution) {
            $size     = $distribution->PosterSize();
            $sizeId   = $size && $size->exists() ? $size->ID : 0;
            $sizeTitles[$sizeId] = $size && $size->exists() ? $size->Title : 'Ohne Größe';

            $distMember = $distribution->Member();
            $memberName = $distMember && $distMember->exists() ? $distMember->getDisplayName() : 'Unbekannt';

            $sizeTotals[$sizeId] = ($sizeTotals[$sizeId] ?? 0) + $distribution->Quantity;

            if (!isset($memberTotals[$memberName])) {
                $memberTotals[$memberName] = [];
            }
            $memberTotals[$memberName][$sizeId] = ($memberTotals[$memberName][$sizeId] ?? 0) + $distribution->Quantity;
        }

        arsort($sizeTotals);
        $sizeIds = array_keys($sizeTotals);

        $memberOrderTotals = [];
        foreach ($memberTotals as $name => $sizes) {
            $memberOrderTotals[$name] = array_sum($sizes);
        }
        arsort($memberOrderTotals);
        $memberLabels = array_keys($memberOrderTotals);

        $sizes = [];
        foreach ($sizeIds as $sizeId) {
            $sizes[] = ['ID' => $sizeId, 'Title' => $sizeTitles[$sizeId], 'Total' => $sizeTotals[$sizeId]];
        }

        $datasetsBySize = [];
        foreach ($sizeIds as $sizeId) {
            $data = [];
            foreach ($memberLabels as $memberName) {
                $data[] = $memberTotals[$memberName][$sizeId] ?? 0;
            }
            $datasetsBySize[] = ['sizeId' => $sizeId, 'size' => $sizeTitles[$sizeId], 'data' => $data];
        }

        return $this->jsonResponse([
            'sizes'          => $sizes,
            'memberLabels'   => $memberLabels,
            'datasetsBySize' => $datasetsBySize,
        ]);
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
            $distribution->Latitude       = $latitude ?: null;
            $distribution->Longitude      = $longitude ?: null;
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
        if (array_key_exists('Latitude', $body)) {
            $distribution->Latitude = trim((string) $body['Latitude']) ?: null;
        }
        if (array_key_exists('Longitude', $body)) {
            $distribution->Longitude = trim((string) $body['Longitude']) ?: null;
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

        if (!$distribution->Location && !($distribution->Latitude && $distribution->Longitude)) {
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
