<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Skript\Script;
use App\Skript\ScriptParagraph;
use App\Skript\ScriptRole;
use App\Skript\ScriptRoleAssignment;
use App\Teams\OrgEvent;
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
        'paragraphSync',
        'orgMembers',
        'eventScripts',
        'eventScriptAttach',
        'eventScriptDetach',
        'eventAssignmentStore',
        'eventAssignmentRemove',
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
     * Rolle mit ihrer Besetzung. Besetzt wird nur über die Rollenverteilung der
     * Events (ScriptRoleAssignment, pro Tag): `EventCasting` gruppiert sie nach Event,
     * `MemberIDs`/`Members` sind alle, die heute oder später besetzt sind — danach
     * richten sich Fokus- und Lernmodus ("deine Rollen").
     */
    private function formatRole(ScriptRole $role): array
    {
        $today = date('Y-m-d');
        $events = [];
        $current = [];
        foreach ($role->Assignments()->sort(['Date' => 'ASC', 'TimeStart' => 'ASC']) as $assignment) {
            $event = $assignment->Event();
            $member = $assignment->Member();
            if (!$event || !$event->exists() || !$member || !$member->exists()) {
                continue;
            }
            if (!isset($events[$event->ID])) {
                $events[$event->ID] = [
                    'EventID'    => $event->ID,
                    'Title'      => $event->Title,
                    'URLSegment' => $event->URLSegment,
                    'IsPast'     => true,
                    'Days'       => [],
                ];
            }
            $day = $assignment->toApi();
            unset($day['RoleID']);
            $events[$event->ID]['Days'][] = $day;
            if ($assignment->Date >= $today) {
                $events[$event->ID]['IsPast'] = false;
                $current[$member->ID] = $this->formatMember($member);
            }
        }

        // Laufende/kommende Events zuerst (nach erstem Tag), vergangene danach (jüngstes zuerst)
        $events = array_values($events);
        usort($events, fn ($a, $b) => ($a['IsPast'] <=> $b['IsPast'])
            ?: ($a['IsPast']
                ? strcmp($b['Days'][0]['Date'], $a['Days'][0]['Date'])
                : strcmp($a['Days'][0]['Date'], $b['Days'][0]['Date'])));

        usort($current, fn ($a, $b) => strnatcasecmp($a['Name'], $b['Name']));

        return [
            'ID'           => $role->ID,
            'Title'        => $role->Title,
            'Description'  => $role->Description,
            'MemberIDs'    => array_column($current, 'ID'),
            'Members'      => array_values($current),
            'EventCasting' => $events,
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
        $script->Events()->removeAll();
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
        $role->Title       = $title;
        $role->Description = trim($body['Description'] ?? '');
        $role->ScriptID    = $script->ID;
        $role->SortOrder   = $script->Roles()->count();
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
        if (isset($body['Description'])) {
            $role->Description = trim($body['Description']);
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

    // ── Skripte & Rollenzuteilung eines Events ────────────────────────────────

    /** Event per ID, nur für Mitglieder seiner Organisation */
    private function findEventFor(int $eventID, Member $member): ?OrgEvent
    {
        $event = $eventID ? OrgEvent::get()->byID($eventID) : null;
        return $event && $event->isInternalFor($member) ? $event : null;
    }

    /** Skripte verknüpfen/lösen darf, wer das Event verwalten oder Skripte bearbeiten darf */
    private function canManageEventScripts(OrgEvent $event, Member $member): bool
    {
        return $event->canBeManagedBy($member)
            || $member->hasOrgPermission($event->Organization(), OrgPermissions::SCRIPT_EDIT);
    }

    /** Aktive Mitglieder der Event-Organisation, die eine Rolle übernehmen können */
    private function eventMembers(OrgEvent $event): array
    {
        $members = [];
        $memberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $event->OrganizationID,
            'Role'           => 'member',
        ]);
        foreach ($memberships as $ms) {
            $formatted = $this->formatMember($ms->Member());
            if ($formatted) {
                $members[] = $formatted;
            }
        }
        usort($members, fn ($a, $b) => strcasecmp($a['Name'], $b['Name']));
        return $members;
    }

    /**
     * Gesamter Stand für den Skript-Bereich der Event-Seite — wird auch nach jeder
     * Änderung zurückgegeben, damit das Frontend einfach neu rendern kann.
     */
    private function formatEventScripts(OrgEvent $event, Member $member): array
    {
        $org = $event->Organization();

        $scripts = [];
        $linkedIDs = [];
        foreach ($event->Scripts()->sort('Title', 'ASC') as $script) {
            $linkedIDs[] = $script->ID;
            $roles = [];
            foreach ($script->Roles() as $role) {
                $roles[] = [
                    'ID'          => $role->ID,
                    'Title'       => $role->Title,
                    'Description' => $role->Description,
                ];
            }
            $scripts[] = [
                'ID'         => $script->ID,
                'Hash'       => $script->Hash,
                'Title'      => $script->Title,
                'CanEdit'    => $script->isEditableBy($member),
                // Andere Events, in denen das Skript ebenfalls verwendet wird
                'OtherEvents' => $script->Events()->exclude('ID', $event->ID)->column('Title'),
                'Roles'      => $roles,
            ];
        }

        $available = [];
        $others = Script::get()->filter('OrganizationID', $event->OrganizationID)->sort('Title', 'ASC');
        if ($linkedIDs) {
            $others = $others->exclude('ID', $linkedIDs);
        }
        foreach ($others as $script) {
            $available[] = [
                'ID'        => $script->ID,
                'Title'     => $script->Title,
                'RoleCount' => $script->Roles()->count(),
            ];
        }

        $assignments = [];
        foreach ($event->RoleAssignments() as $assignment) {
            $assignments[] = $assignment->toApi();
        }

        return [
            'scripts'          => $scripts,
            'castingDays'      => $this->castingDays($event),
            'availableScripts' => $available,
            'assignments'      => $assignments,
            'members'          => $this->eventMembers($event),
            'CanManageScripts' => $this->canManageEventScripts($event, $member),
            'CanCreateScripts' => $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::SCRIPT_EDIT),
            'CanAssign'        => $org && $org->exists() && $this->canManageRoles($org, $member),
        ];
    }

    /**
     * Tage, an denen Rollen zugeteilt werden: alle Tage der Termine des Events mit
     * eingeschaltetem Rollenplan. Pro Tag die Termine (für Beschriftung und als
     * Vorschlag für die Uhrzeit, wenn nicht ganztägig zugeteilt wird) und wer für
     * diesen Tag zugesagt hat — Available: [{MemberID, Windows}], Windows ist null
     * ohne eigenen Zeitraum, sonst die zugesagten Zeitfenster [{TimeStart, TimeEnd}].
     * @return array<int, array{Date: string, Appointments: array, Available: array}>
     */
    private function castingDays(OrgEvent $event): array
    {
        $days = [];
        $available = [];
        $appointments = $event->Appointments()
            ->filter('EnableRoleCasting', true)
            ->exclude('Status', 'Cancelled')
            ->sort(['DateStart' => 'ASC', 'TimeStart' => 'ASC']);
        foreach ($appointments as $appointment) {
            $start = $appointment->DateStart;
            $end = $appointment->DateEnd ?: $start;
            // Mehrtägige Termine: jeder Tag einzeln (höchstens 31)
            for ($day = $start, $i = 0; $day && $day <= $end && $i < 31; $day = date('Y-m-d', strtotime($day . ' +1 day')), $i++) {
                $days[$day]['Date'] = $day;
                $days[$day]['Appointments'][] = [
                    'ID'        => $appointment->ID,
                    'Title'     => $appointment->Title,
                    // Uhrzeiten nur am ersten bzw. letzten Tag eines mehrtägigen Termins
                    'TimeStart' => !$appointment->AllDay && $day === $start && $appointment->TimeStart ? substr($appointment->TimeStart, 0, 5) : null,
                    'TimeEnd'   => !$appointment->AllDay && $day === $end && $appointment->TimeEnd ? substr($appointment->TimeEnd, 0, 5) : null,
                ];
                // Nur echte Zusagen; ein eigener Zeitraum gilt für jeden Tag des Termins
                foreach ($appointment->Participations()->filter('Type', 'Accept') as $participation) {
                    $memberID = (int) $participation->MemberID;
                    $window = $participation->CustomTimeframe && $participation->TimeStart && $participation->TimeEnd
                        ? ['TimeStart' => substr($participation->TimeStart, 0, 5), 'TimeEnd' => substr($participation->TimeEnd, 0, 5)]
                        : null;
                    if (!array_key_exists($memberID, $available[$day] ?? [])) {
                        $available[$day][$memberID] = $window ? [$window] : null;
                    } elseif ($available[$day][$memberID] !== null) {
                        // Ohne eigenen Zeitraum bei einem Termin: den ganzen Tag verfügbar
                        $available[$day][$memberID] = $window ? [...$available[$day][$memberID], $window] : null;
                    }
                }
            }
        }
        foreach ($days as $day => $data) {
            $days[$day]['Available'] = [];
            foreach ($available[$day] ?? [] as $memberID => $windows) {
                $days[$day]['Available'][] = ['MemberID' => $memberID, 'Windows' => $windows];
            }
        }
        ksort($days);
        return array_values($days);
    }

    /** GET /api/v1/skript/eventScripts/$ID — Skripte, Rollen und Rollenzuteilung eines Events */
    public function eventScripts(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $event = $this->findEventFor((int) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        return $this->jsonResponse($this->formatEventScripts($event, $member));
    }

    /**
     * POST /api/v1/skript/eventScriptAttach/$ID
     * Body {ScriptID} verknüpft ein vorhandenes Skript der Organisation,
     * Body {Title} legt ein neues Skript an und verknüpft es direkt.
     */
    public function eventScriptAttach(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $event = $this->findEventFor((int) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        if (!$this->canManageEventScripts($event, $member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $scriptID = (int) ($body['ScriptID'] ?? 0);
        $title = trim($body['Title'] ?? '');

        if ($scriptID) {
            $script = Script::get()->filter(['ID' => $scriptID, 'OrganizationID' => $event->OrganizationID])->first();
            if (!$script) {
                return $this->errorResponse('Skript nicht gefunden', 404);
            }
        } elseif ($title !== '') {
            if (!$member->hasOrgPermission($event->Organization(), OrgPermissions::SCRIPT_EDIT)) {
                return $this->errorResponse('Keine Berechtigung, Skripte anzulegen', 403);
            }
            $script = Script::create();
            $script->Title = $title;
            $script->OrganizationID = $event->OrganizationID;
            $script->write();
        } else {
            return $this->errorResponse('Skript oder Titel ist erforderlich', 400);
        }

        $event->Scripts()->add($script);

        return $this->successResponse($this->formatEventScripts($event, $member), 'Skript hinzugefügt');
    }

    /**
     * DELETE /api/v1/skript/eventScriptDetach/$ID?script={ScriptID}
     * Löst das Skript vom Event (das Skript bleibt erhalten) und entfernt die
     * Rollenzuteilungen seiner Rollen in diesem Event.
     */
    public function eventScriptDetach(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $event = $this->findEventFor((int) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        if (!$this->canManageEventScripts($event, $member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $script = $event->Scripts()->byID((int) $request->getVar('script'));
        if (!$script) {
            return $this->errorResponse('Skript ist diesem Event nicht zugeordnet', 404);
        }

        $roleIDs = $script->Roles()->column('ID');
        if ($roleIDs) {
            foreach ($event->RoleAssignments()->filter('RoleID', $roleIDs) as $assignment) {
                $assignment->delete();
            }
        }
        $event->Scripts()->remove($script);

        return $this->successResponse($this->formatEventScripts($event, $member), 'Skript entfernt');
    }

    /**
     * POST /api/v1/skript/eventAssignmentStore/$ID
     * Body {RoleID, MemberID, Date: 'Y-m-d', TimeStart?: 'H:i', TimeEnd?: 'H:i'} —
     * ohne Uhrzeiten gilt die Zuteilung für den ganzen Tag.
     */
    public function eventAssignmentStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $event = $this->findEventFor((int) $request->param('ID'), $member);
        if (!$event) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }
        if (!$this->canManageRoles($event->Organization(), $member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();

        // Nur Rollen der Skripte, die mit diesem Event verknüpft sind
        $role = ScriptRole::get()->byID((int) ($body['RoleID'] ?? 0));
        if (!$role || !$event->Scripts()->byID($role->ScriptID)) {
            return $this->errorResponse('Rolle gehört zu keinem Skript dieses Events', 400);
        }

        $memberID = (int) ($body['MemberID'] ?? 0);
        $isMember = $memberID && OrganizationMembership::get()->filter([
            'OrganizationID' => $event->OrganizationID,
            'Role'           => 'member',
            'MemberID'       => $memberID,
        ])->exists();
        if (!$isMember) {
            return $this->errorResponse('Bitte ein Mitglied der Organisation auswählen', 400);
        }

        $date = (string) ($body['Date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->errorResponse('Bitte einen Tag angeben', 400);
        }

        $timeStart = trim((string) ($body['TimeStart'] ?? ''));
        $timeEnd = trim((string) ($body['TimeEnd'] ?? ''));
        foreach ([$timeStart, $timeEnd] as $time) {
            if ($time !== '' && !preg_match('/^\d{2}:\d{2}$/', $time)) {
                return $this->errorResponse('Ungültige Uhrzeit', 400);
            }
        }
        if (($timeStart === '') !== ($timeEnd === '')) {
            return $this->errorResponse('Bitte Beginn und Ende angeben — oder beides leer lassen für den ganzen Tag', 400);
        }
        if ($timeStart !== '' && $timeEnd <= $timeStart) {
            return $this->errorResponse('Das Ende muss nach dem Beginn liegen', 400);
        }

        // Zuteilen lässt sich nur, wer für einen Termin mit Rollenplan an diesem Tag zugesagt hat
        $day = current(array_filter($this->castingDays($event), fn ($d) => $d['Date'] === $date));
        if (!$day || !in_array($memberID, array_column($day['Available'], 'MemberID'), true)) {
            return $this->errorResponse('Diese Person hat für keinen Termin mit Rollenplan an diesem Tag zugesagt', 400);
        }

        // Niemand kann zwei Rollen gleichzeitig spielen — auch nicht in verschiedenen Events
        $conflict = $this->findOverlappingAssignment($memberID, $date, $timeStart ?: null, $timeEnd ?: null);
        if ($conflict) {
            $who = $conflict->Member()->getDisplayName();
            $when = $conflict->TimeStart
                ? substr($conflict->TimeStart, 0, 5) . ' – ' . substr($conflict->TimeEnd, 0, 5)
                : 'den ganzen Tag';
            $where = (int) $conflict->EventID === $event->ID ? '' : ' (Event „' . $conflict->Event()->Title . '“)';
            return $this->errorResponse("$who spielt an diesem Tag $when schon „{$conflict->Role()->Title}“$where.", 400);
        }

        $assignment = ScriptRoleAssignment::create();
        $assignment->EventID = $event->ID;
        $assignment->RoleID = $role->ID;
        $assignment->MemberID = $memberID;
        $assignment->Date = $date;
        $assignment->TimeStart = $timeStart ?: null;
        $assignment->TimeEnd = $timeEnd ?: null;
        $assignment->write();

        return $this->successResponse($this->formatEventScripts($event, $member), 'Rolle zugeteilt');
    }

    /**
     * Erste Zuteilung des Mitglieds am selben Tag, die sich zeitlich überschneidet —
     * ganztägig (ohne Uhrzeit) überschneidet sich mit allem. Zeiten als 'H:i'.
     */
    private function findOverlappingAssignment(int $memberID, string $date, ?string $timeStart, ?string $timeEnd): ?ScriptRoleAssignment
    {
        $sameDay = ScriptRoleAssignment::get()->filter(['MemberID' => $memberID, 'Date' => $date]);
        foreach ($sameDay as $other) {
            if (!$timeStart || !$other->TimeStart) {
                return $other;
            }
            $otherStart = substr($other->TimeStart, 0, 5);
            $otherEnd = substr($other->TimeEnd, 0, 5);
            if ($timeStart < $otherEnd && $otherStart < $timeEnd) {
                return $other;
            }
        }
        return null;
    }

    /** DELETE /api/v1/skript/eventAssignmentRemove/$ID — ID der Zuteilung */
    public function eventAssignmentRemove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $assignment = ScriptRoleAssignment::get()->byID((int) $request->param('ID'));
        $event = $assignment ? $this->findEventFor((int) $assignment->EventID, $member) : null;
        if (!$event) {
            return $this->errorResponse('Zuteilung nicht gefunden', 404);
        }
        if (!$this->canManageRoles($event->Organization(), $member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $assignment->delete();

        return $this->successResponse($this->formatEventScripts($event, $member), 'Zuteilung entfernt');
    }
}
