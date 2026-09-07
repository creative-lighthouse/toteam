<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Skript\Script;
use App\Skript\ScriptParagraph;
use App\Skript\ScriptRole;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\SkriptApiController
 *
 */
class SkriptApiController extends ApiController
{
    private static $url_segment = 'api/v1/skript';

    private static $allowed_actions = [
        'index',
        'detail',
        'store',
        'update',
        'remove',
        'roleStore',
        'roleUpdate',
        'roleRemove',
        'roleAssignMembers',
        'paragraphSync',
        'orgMembers',
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

    private function formatRole(ScriptRole $role): array
    {
        $memberIDs = [];
        $members   = [];
        foreach ($role->Members() as $m) {
            $memberIDs[] = $m->ID;
            $members[]   = $this->formatMember($m);
        }

        return [
            'ID'        => $role->ID,
            'Title'     => $role->Title,
            'MemberIDs' => $memberIDs,
            'Members'   => $members,
        ];
    }

    private function formatParagraph(ScriptParagraph $paragraph, ?int $lineNumber): array
    {
        return [
            'ID'          => $paragraph->ID,
            'LineNumber'  => $lineNumber,
            'Content'     => $paragraph->Content,
            'RoleIDs'     => $paragraph->Roles()->column('ID'),
            'IsDirection' => (bool) $paragraph->IsDirection,
        ];
    }

    /**
     * Formats all of a script's paragraphs in order, numbering only the ones
     * that are neither stage directions (IsDirection) nor headings — both are
     * skipped in the running line count and get no LineNumber at all.
     * @return array<int, array>
     */
    private function formatParagraphs(Script $script): array
    {
        $paragraphs = [];
        $line = 1;
        foreach ($script->Paragraphs() as $paragraph) {
            if ($paragraph->IsDirection || $this->isHeadingContent($paragraph->Content)) {
                $paragraphs[] = $this->formatParagraph($paragraph, null);
                continue;
            }
            $paragraphs[] = $this->formatParagraph($paragraph, $line);
            $line++;
        }
        return $paragraphs;
    }

    private function isHeadingContent(?string $content): bool
    {
        return (bool) preg_match('/^\s*<h[234]\b/i', (string) $content);
    }

    private function formatScript(Script $script, Member $member, bool $withDetails = true): array
    {
        $org = $script->Organization();

        $data = [
            'ID'           => $script->ID,
            'Hash'         => $script->Hash,
            'Title'        => $script->Title,
            'Organization' => $org && $org->exists() ? [
                'ID'      => $org->ID,
                'Title'   => $org->Title,
                'LogoURL' => $org->RenderLogo(40),
            ] : null,
            'CanEdit'      => $script->isEditableBy($member),
            'CanDelete'    => $script->isDeletableBy($member),
            'CanManageRoles' => $org && $org->exists() && (
                $script->isEditableBy($member) || $member->hasOrgPermission($org, OrgPermissions::SCRIPT_MANAGE_ROLES)
            ),
        ];

        if ($withDetails) {
            $roles = [];
            foreach ($script->Roles() as $role) {
                $roles[] = $this->formatRole($role);
            }

            $data['Roles']      = $roles;
            $data['Paragraphs'] = $this->formatParagraphs($script);
        }

        return $data;
    }

    private function canManageRoles(Organization $org, Member $member): bool
    {
        return $member->hasOrgPermission($org, OrgPermissions::SCRIPT_EDIT)
            || $member->hasOrgPermission($org, OrgPermissions::SCRIPT_MANAGE_ROLES);
    }

    /** GET /api/v1/skript */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = $member->getOrganizationIDs();

        $scripts = Script::get()->filter('OrganizationID', $orgIDs ?: [0])->sort('Created DESC');

        if ($orgID = (int) $request->getVar('organization')) {
            $scripts = $scripts->filter('OrganizationID', $orgID);
        }

        $data = [];
        foreach ($scripts as $script) {
            $data[] = $this->formatScript($script, $member, false);
        }

        $orgData = [];
        foreach ($orgIDs as $oid) {
            $org = Organization::get()->byID($oid);
            if ($org) {
                $orgData[] = ['ID' => $org->ID, 'Title' => $org->Title, 'LogoURL' => $org->RenderLogo(40)];
            }
        }

        return $this->jsonResponse(['scripts' => $data, 'organizations' => $orgData]);
    }

    /** GET /api/v1/skript/detail?hash=... */
    public function detail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $hash = $request->getVar('hash');
        $id   = (int) $request->param('ID');

        $script = $hash
            ? Script::get()->filter('Hash', $hash)->first()
            : ($id ? Script::get()->byID($id) : null);

        if (!$script || !$script->exists()) {
            return $this->errorResponse('Skript nicht gefunden', 404);
        }

        if (!$script->isViewableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        return $this->jsonResponse(['script' => $this->formatScript($script, $member)]);
    }

    /** GET /api/v1/skript/orgMembers/$ID */
    public function orgMembers(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgID = (int) $request->param('ID');
        if (!$orgID || !in_array($orgID, $member->getOrganizationIDs(), true)) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        $memberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $orgID,
            'Role'           => 'member',
        ]);

        $members = [];
        foreach ($memberships as $ms) {
            $formatted = $this->formatMember($ms->Member());
            if ($formatted) {
                $members[] = $formatted;
            }
        }

        return $this->jsonResponse(['members' => $members]);
    }

    /** POST /api/v1/skript/store */
    public function store(HTTPRequest $request): HTTPResponse
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
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::SCRIPT_EDIT)) {
            return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
        }

        try {
            $script = Script::create();
            $script->Title          = $title;
            $script->OrganizationID = $orgID;
            $script->write();

            return $this->successResponse(['script' => $this->formatScript($script, $member)], 'Skript erstellt');
        } catch (\Exception $e) {
            error_log('SkriptApiController::store error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Erstellen des Skripts', 500);
        }
    }

    /** PUT /api/v1/skript/update/$ID */
    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $script = Script::get()->byID((int) $request->param('ID'));
        if (!$script || !$script->exists()) {
            return $this->errorResponse('Skript nicht gefunden', 404);
        }
        if (!$script->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        if (isset($body['Title'])) {
            $script->Title = trim($body['Title']);
        }
        $script->write();

        return $this->successResponse(['script' => $this->formatScript($script, $member)], 'Skript aktualisiert');
    }

    /** DELETE /api/v1/skript/remove/$ID */
    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $script = Script::get()->byID((int) $request->param('ID'));
        if (!$script || !$script->exists()) {
            return $this->errorResponse('Skript nicht gefunden', 404);
        }
        if (!$script->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        foreach ($script->Paragraphs() as $paragraph) {
            $paragraph->delete();
        }
        foreach ($script->Roles() as $role) {
            $role->delete();
        }
        $script->delete();

        return $this->successResponse([], 'Skript gelöscht');
    }

    /** POST /api/v1/skript/roleStore */
    public function roleStore(HTTPRequest $request): HTTPResponse
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

        $script = Script::get()->byID((int) ($body['ScriptID'] ?? 0));
        if (!$script || !$script->exists()) {
            return $this->errorResponse('Skript nicht gefunden', 404);
        }
        if (!$script->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $role = ScriptRole::create();
        $role->Title      = $title;
        $role->ScriptID   = $script->ID;
        $role->SortOrder  = $script->Roles()->count();
        $role->write();

        return $this->successResponse(['role' => $this->formatRole($role)], 'Rolle erstellt');
    }

    /** PUT /api/v1/skript/roleUpdate/$ID */
    public function roleUpdate(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $role = ScriptRole::get()->byID((int) $request->param('ID'));
        if (!$role || !$role->exists()) {
            return $this->errorResponse('Rolle nicht gefunden', 404);
        }
        if (!$role->Script()->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        if (isset($body['Title'])) {
            $role->Title = trim($body['Title']);
        }
        $role->write();

        return $this->successResponse(['role' => $this->formatRole($role)], 'Rolle aktualisiert');
    }

    /** DELETE /api/v1/skript/roleRemove/$ID */
    public function roleRemove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $role = ScriptRole::get()->byID((int) $request->param('ID'));
        if (!$role || !$role->exists()) {
            return $this->errorResponse('Rolle nicht gefunden', 404);
        }
        if (!$role->Script()->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $role->delete();

        return $this->successResponse([], 'Rolle gelöscht');
    }

    /** PUT /api/v1/skript/roleAssignMembers/$ID — body {MemberIDs: number[]} */
    public function roleAssignMembers(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $role = ScriptRole::get()->byID((int) $request->param('ID'));
        if (!$role || !$role->exists()) {
            return $this->errorResponse('Rolle nicht gefunden', 404);
        }

        $script = $role->Script();
        $org    = $script->Organization();
        if (!$org || !$org->exists() || !$this->canManageRoles($org, $member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body      = $this->getJsonBody();
        $memberIDs = is_array($body['MemberIDs'] ?? null) ? array_map('intval', $body['MemberIDs']) : [];

        // Nur Mitglieder der Skript-Organisation dürfen zugewiesen werden
        // (filter() on an empty ID array throws in this ORM version, so an
        // empty selection — unassigning everyone — is short-circuited here)
        $validMemberIDs = $memberIDs ? OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'Role'           => 'member',
            'MemberID'       => $memberIDs,
        ])->column('MemberID') : [];

        $role->Members()->setByIDList($validMemberIDs);

        return $this->successResponse(['role' => $this->formatRole($role)], 'Zuweisung gespeichert');
    }

    /**
     * PUT /api/v1/skript/paragraphSync — body {ScriptID, Paragraphs: [{ID?, Content, RoleIDs?}]}
     *
     * Der Editor schreibt ein durchgehendes Dokument, aus dem das Frontend bei
     * jeder Änderung die aktuelle Liste der Absätze (in Dokumentreihenfolge)
     * extrahiert und komplett hier abgleicht: bekannte IDs werden aktualisiert,
     * Einträge ohne (oder mit unbekannter) ID werden neu angelegt, Absätze, die
     * nicht mehr im Dokument vorkommen, werden gelöscht. Die Antwort liefert die
     * kanonische, ID-vollständige Liste in derselben Reihenfolge zurück, damit
     * das Frontend die neu vergebenen IDs auf die zugehörigen Editor-Knoten
     * zurückschreiben kann.
     */
    public function paragraphSync(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body   = $this->getJsonBody();
        $script = Script::get()->byID((int) ($body['ScriptID'] ?? 0));
        if (!$script || !$script->exists()) {
            return $this->errorResponse('Skript nicht gefunden', 404);
        }
        if (!$script->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $incoming    = is_array($body['Paragraphs'] ?? null) ? $body['Paragraphs'] : [];
        $existingIDs = ScriptParagraph::get()->filter('ScriptID', $script->ID)->column('ID');

        $keptIDs   = [];
        $sortOrder = 0;

        foreach ($incoming as $entry) {
            $id          = (int) ($entry['ID'] ?? 0);
            $content     = $entry['Content'] ?? '';
            $isDirection = !empty($entry['IsDirection']);
            // A stage direction has no speaker, so any incoming role
            // assignment is ignored for it rather than trusted from the client.
            $roleIDs = (!$isDirection && is_array($entry['RoleIDs'] ?? null)) ? array_map('intval', $entry['RoleIDs']) : [];

            $paragraph = ($id && in_array($id, $existingIDs, true))
                ? ScriptParagraph::get()->byID($id)
                : ScriptParagraph::create();

            $paragraph->Content     = $content;
            $paragraph->IsDirection = $isDirection;
            $paragraph->ScriptID    = $script->ID;
            $paragraph->SortOrder   = $sortOrder;
            $paragraph->write();

            $validRoleIDs = $roleIDs ? ScriptRole::get()->filter(['ID' => $roleIDs, 'ScriptID' => $script->ID])->column('ID') : [];
            $paragraph->Roles()->setByIDList($validRoleIDs);

            $keptIDs[] = $paragraph->ID;
            $sortOrder++;
        }

        foreach (ScriptParagraph::get()->filter('ScriptID', $script->ID) as $paragraph) {
            if (!in_array($paragraph->ID, $keptIDs, true)) {
                $paragraph->delete();
            }
        }

        return $this->successResponse(['paragraphs' => $this->formatParagraphs($script)], 'Skript gespeichert');
    }
}
