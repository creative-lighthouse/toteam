<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use App\Teams\OrgRole;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Class \App\Controllers\Api\OrgRolesApiController
 *
 */
class OrgRolesApiController extends ApiController
{
    private static $url_segment = 'api/v1/orgroles';

    private static $allowed_actions = [
        'catalogue',
        'index',
        'store',
        'update',
        'remove',
        'assignToMember',
    ];

    protected function getDefaultAction()
    {
        return 'catalogue';
    }

    /** GET /api/v1/orgroles/catalogue */
    public function catalogue(HTTPRequest $request): HTTPResponse
    {
        if (!$this->requireAuth()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        return $this->jsonResponse(['categories' => OrgPermissions::categories()]);
    }

    /** GET /api/v1/orgroles/index/$OrgID */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $org = Organization::get()->byID((int) $request->param('ID'));
        if (!$org || !$org->exists()) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        if (!$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_ROLES)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $roles = [];
        foreach (OrgRole::get()->filter('OrganizationID', $org->ID) as $role) {
            $roles[] = $this->formatRole($role);
        }

        return $this->jsonResponse(['roles' => $roles]);
    }

    /** POST /api/v1/orgroles/store */
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
        $title = trim($body['Title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $org = Organization::get()->byID((int) ($body['OrganizationID'] ?? 0));
        if (!$org || !$org->exists()) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        if (!$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_ROLES)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $role = OrgRole::create();
        $role->Title = $title;
        $role->OrganizationID = $org->ID;
        $role->SortOrder = OrgRole::get()->filter('OrganizationID', $org->ID)->count();
        $role->setPermissionCodes($body['Permissions'] ?? []);
        $role->write();

        return $this->successResponse(['role' => $this->formatRole($role)], 'Rolle erstellt');
    }

    /** PUT /api/v1/orgroles/update/$ID */
    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $role = OrgRole::get()->byID((int) $request->param('ID'));
        if (!$role || !$role->exists()) {
            return $this->errorResponse('Rolle nicht gefunden', 404);
        }

        $org = $role->Organization();
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_ROLES)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();

        if (isset($body['Permissions'])) {
            $newCodes = is_array($body['Permissions']) ? $body['Permissions'] : [];
            $losesAdmin = $role->hasPermission(OrgPermissions::ORG_ADMIN)
                && !in_array(OrgPermissions::ORG_ADMIN, $newCodes, true);

            if ($losesAdmin && $org->adminHolderCount([$role->ID]) === 0) {
                return $this->errorResponse('Es muss immer mindestens eine Person mit Administrator-Rechten geben.', 400);
            }

            $role->setPermissionCodes($newCodes);
        }

        if (isset($body['Title'])) {
            $title = trim($body['Title']);
            if (!$title) {
                return $this->errorResponse('Titel ist erforderlich', 400);
            }
            $role->Title = $title;
        }

        $role->write();

        return $this->successResponse(['role' => $this->formatRole($role)], 'Rolle aktualisiert');
    }

    /** DELETE /api/v1/orgroles/remove/$ID */
    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $role = OrgRole::get()->byID((int) $request->param('ID'));
        if (!$role || !$role->exists()) {
            return $this->errorResponse('Rolle nicht gefunden', 404);
        }

        $org = $role->Organization();
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_ROLES)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        if ($role->hasPermission(OrgPermissions::ORG_ADMIN) && $org->adminHolderCount([$role->ID]) === 0) {
            return $this->errorResponse('Es muss immer mindestens eine Person mit Administrator-Rechten geben.', 400);
        }

        $role->Memberships()->removeAll();
        $role->delete();

        return $this->successResponse([], 'Rolle gelöscht');
    }

    /** PUT /api/v1/orgroles/assignToMember/$ID ($ID = MembershipID) */
    public function assignToMember(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $membership = OrganizationMembership::get()->byID((int) $request->param('ID'));
        if (!$membership || !$membership->exists()) {
            return $this->errorResponse('Mitgliedschaft nicht gefunden', 404);
        }

        $org = $membership->Organization();
        if (!$org || !$org->exists()) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        $canAssign = $member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)
            || $member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_ROLES);
        if (!$canAssign) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $roleIDs = array_unique(array_map('intval', is_array($body['RoleIDs'] ?? null) ? $body['RoleIDs'] : []));

        $roles = empty($roleIDs)
            ? OrgRole::get()->filter('ID', -1) // leere Liste, ohne die ORM-Filter-Exception bei leerem $roleIDs auszulösen
            : OrgRole::get()->filter(['ID' => $roleIDs, 'OrganizationID' => $org->ID]);
        if ($roles->count() !== count($roleIDs)) {
            return $this->errorResponse('Ungültige Rollenauswahl', 400);
        }

        $stillHasAdmin = false;
        foreach ($roles as $role) {
            if ($role->hasPermission(OrgPermissions::ORG_ADMIN)) {
                $stillHasAdmin = true;
                break;
            }
        }

        // War diese Mitgliedschaft bisher (mit-)verantwortlich für die letzte Admin-Zuweisung?
        $adminRoleIDs = $this->adminRoleIDs($org);
        $wasAdminHolder = !empty($adminRoleIDs) && $membership->Roles()->filter('ID', $adminRoleIDs)->exists();
        if ($wasAdminHolder && !$stillHasAdmin && $org->adminHolderCount([], [$membership->ID]) === 0) {
            return $this->errorResponse('Es muss immer mindestens eine Person mit Administrator-Rechten geben.', 400);
        }

        $membership->Roles()->setByIDList($roleIDs);

        return $this->successResponse([
            'membership' => [
                'ID'    => $membership->ID,
                'Roles' => $this->formatMembershipRoles($membership),
            ],
        ], 'Rollen aktualisiert');
    }

    private function adminRoleIDs(Organization $org): array
    {
        $ids = [];
        foreach (OrgRole::get()->filter('OrganizationID', $org->ID) as $role) {
            if ($role->hasPermission(OrgPermissions::ORG_ADMIN)) {
                $ids[] = $role->ID;
            }
        }
        return $ids;
    }

    private function formatMembershipRoles(OrganizationMembership $membership): array
    {
        $data = [];
        foreach ($membership->Roles() as $role) {
            $data[] = ['ID' => $role->ID, 'Title' => $role->Title];
        }
        return $data;
    }

    private function formatRole(OrgRole $role): array
    {
        return [
            'ID'          => $role->ID,
            'Title'       => $role->Title,
            'Permissions' => $role->getPermissionCodes(),
            'MemberCount' => $role->Memberships()->count(),
        ];
    }
}
