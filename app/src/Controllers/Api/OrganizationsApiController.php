<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Notifications\PushNotificationService;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\OrganizationsApiController
 *
 */
class OrganizationsApiController extends ApiController
{
    private static $url_segment = 'api/v1/organizations';

    private static $allowed_actions = [
        'index',
        'detail',
        'store',
        'join',
        'applicants',
        'accept',
        'reject',
        'uploadLogo',
        'pendingCount',
        'invite',
        'cancelInvitation',
        'invitations',
        'respondInvitation',
        'inviteSearch',
        'removeMember',
        'leave',
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $organizations = Organization::get()->exclude('JoinMode', 'hidden')->sort('Title ASC');
        $data = [];

        foreach ($organizations as $org) {
            $memberCount = OrganizationMembership::get()->filter([
                'OrganizationID' => $org->ID,
                'Role'           => 'member',
            ])->count();

            $membership = OrganizationMembership::get()->filter([
                'OrganizationID' => $org->ID,
                'MemberID'       => $member->ID,
            ])->first();

            $myRole = $membership ? $membership->Role : null;

            $myRoleNames = [];
            if ($membership) {
                foreach ($membership->Roles() as $role) {
                    $myRoleNames[] = $role->Title;
                }
            }

            $applicantCount = $member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)
                ? OrganizationMembership::get()->filter([
                    'OrganizationID' => $org->ID,
                    'Role'           => 'applicant',
                ])->count()
                : null;

            $data[] = [
                'ID'                   => $org->ID,
                'Username'             => $org->Username ?: null,
                'Title'                => $org->Title,
                'Description'          => $org->Description,
                'LogoURL'              => $org->RenderLogo(80),
                'CoverURL'             => $org->CoverImage()->exists() ? $org->CoverImage()->ScaleWidth(600)->getURL() : null,
                'JoinMode'             => $org->JoinMode,
                'MemberCount'          => $memberCount,
                'MembershipStatus'     => $myRole,
                'MyRoleNames'          => $myRoleNames,
                'ApplicantCount'       => $applicantCount,
                'Permissions'          => $member->getOrgPermissionCodes($org),
                'CalendarManagerRoles' => $org->rolesWithPermission(OrgPermissions::CALENDAR_MANAGE),
            ];
        }

        return $this->jsonResponse(['organizations' => $data]);
    }

    public function detail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $username = $request->getVar('username');
        if (!$username) {
            return $this->errorResponse('Benutzername erforderlich', 400);
        }

        $org = Organization::get()->filter('Username', $username)->first();
        if (!$org || $org->JoinMode === 'hidden') {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        $membership = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'MemberID'       => $member->ID,
        ])->first();
        $myRole = $membership ? $membership->Role : null;

        $memberCount = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'Role'           => 'member',
        ])->count();

        $canManageMembers = $member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS);
        $canManageRoles = $member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_ROLES);

        $applicantCount = $canManageMembers
            ? OrganizationMembership::get()->filter([
                'OrganizationID' => $org->ID,
                'Role'           => 'applicant',
            ])->count()
            : null;

        $memberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'Role'           => 'member',
        ]);

        $members = [];
        foreach ($memberships as $ms) {
            $m = $ms->Member();
            if (!$m) {
                continue;
            }
            $roles = [];
            foreach ($ms->Roles() as $role) {
                $roles[] = ['ID' => $role->ID, 'Title' => $role->Title];
            }
            $members[] = [
                'MembershipID' => $ms->ID,
                'MemberID'     => $m->ID,
                'Name'         => $m->getDisplayName(),
                'Avatar'       => $m->RenderProfileImage(),
                'Username'     => $m->Username ?: null,
                'Roles'        => $roles,
                // Administratoren kann nur ein Administrator entfernen (removeMember)
                'IsAdmin'      => $m->isAdminOfOrg($org),
            ];
        }
        usort($members, fn($a, $b) => strcasecmp($a['Name'], $b['Name']));

        // Offene Einladungen (nur für alle, die Mitglieder verwalten dürfen)
        $invitations = [];
        if ($canManageMembers) {
            foreach (OrganizationMembership::get()->filter(['OrganizationID' => $org->ID, 'Role' => 'invited']) as $inv) {
                $m = $inv->Member();
                if (!$m || !$m->exists()) {
                    continue;
                }
                $by = $inv->InvitedBy();
                $invitations[] = [
                    'MembershipID' => $inv->ID,
                    'MemberID'     => $m->ID,
                    'Name'         => $m->getDisplayName(),
                    'Avatar'       => $m->RenderProfileImage(),
                    'Username'     => $m->Username ?: null,
                    'InvitedBy'    => $by && $by->exists() ? $by->getDisplayName() : null,
                    'Created'      => $inv->Created,
                ];
            }
        }

        return $this->jsonResponse([
            'organization' => [
                'ID'               => $org->ID,
                'Username'         => $org->Username ?: null,
                'Title'            => $org->Title,
                'Description'      => $org->Description,
                'LogoURL'          => $org->RenderLogo(200),
                'CoverURL'         => $org->CoverImage()->exists() ? $org->CoverImage()->ScaleWidth(1200)->getURL() : null,
                'JoinMode'         => $org->JoinMode,
                'MemberCount'      => $memberCount,
                'MembershipStatus' => $myRole,
                'ApplicantCount'   => $applicantCount,
                'Members'          => $members,
                'Invitations'      => $invitations,
                'CanManageMembers' => $canManageMembers,
                'CanManageRoles'   => $canManageRoles,
                'Permissions'      => $member->getOrgPermissionCodes($org),
            ],
        ]);
    }

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

        $username = trim($body['Username'] ?? '');

        $org = Organization::create();
        $org->Title = $title;
        $org->Description = trim($body['Description'] ?? '');
        $org->JoinMode = in_array($body['JoinMode'] ?? '', ['open', 'application', 'invite_only', 'hidden'], true)
            ? $body['JoinMode']
            : 'invite_only';
        $org->Username = $username ?: null;

        $result = $org->validate();
        if (!$result->isValid()) {
            $messages = array_map(fn($e) => $e['message'], $result->getMessages());
            return $this->errorResponse(implode(' ', $messages), 400);
        }

        $org->write();

        $adminRole = null;
        foreach ($org->OrgRoles() as $role) {
            if ($role->hasPermission(OrgPermissions::ORG_ADMIN)) {
                $adminRole = $role;
                break;
            }
        }

        $membership = OrganizationMembership::create();
        $membership->OrganizationID = $org->ID;
        $membership->MemberID = $member->ID;
        $membership->Role = 'member';
        $membership->write();

        if ($adminRole) {
            $membership->Roles()->add($adminRole);
        }

        return $this->successResponse([
            'organization' => [
                'ID'                   => $org->ID,
                'Username'             => $org->Username ?: null,
                'Title'                => $org->Title,
                'Description'          => $org->Description,
                'LogoURL'              => $org->RenderLogo(80),
                'CoverURL'             => null,
                'JoinMode'             => $org->JoinMode,
                'MemberCount'          => 1,
                'MembershipStatus'     => 'member',
                'MyRoleNames'          => $adminRole ? [$adminRole->Title] : [],
                'ApplicantCount'       => 0,
                'Permissions'          => $member->getOrgPermissionCodes($org),
                'CalendarManagerRoles' => $org->rolesWithPermission(OrgPermissions::CALENDAR_MANAGE),
            ],
        ], 'Organisation erstellt');
    }

    public function join(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $orgID = (int) $request->param('ID');
        $org = Organization::get()->byID($orgID);

        if (!$org) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        $existing = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'MemberID'       => $member->ID,
        ])->first();

        // Eingeladen: "Beitreten" nimmt die Einladung an (auch bei Organisationen nur auf Einladung)
        if ($existing && $existing->isInvited()) {
            $this->acceptInvitation($existing);
            return $this->successResponse(['MembershipStatus' => 'member'], 'Einladung angenommen');
        }

        if ($org->JoinMode === 'invite_only') {
            return $this->errorResponse('Diese Organisation nimmt keine Bewerbungen an', 403);
        }

        if ($existing) {
            return $this->errorResponse('Du bist bereits Mitglied oder hast dich bereits beworben', 409);
        }

        $role = $org->JoinMode === 'open' ? 'member' : 'applicant';

        $membership = OrganizationMembership::create();
        $membership->OrganizationID = $org->ID;
        $membership->MemberID       = $member->ID;
        $membership->Role           = $role;
        $membership->write();

        if ($role === 'member') {
            if ($defaultRole = $org->getDefaultRole()) {
                $membership->Roles()->add($defaultRole);
            }
        } else {
            PushNotificationService::notifyNewApplication($membership);
        }

        return $this->successResponse([
            'MembershipStatus' => $role,
        ], $role === 'member' ? 'Erfolgreich beigetreten' : 'Bewerbung erfolgreich eingereicht');
    }

    // ── Einladungen ──────────────────────────────────────────────────────────

    /** Einladung annehmen: Mitglied mit der Standardrolle der Organisation */
    private function acceptInvitation(OrganizationMembership $membership): void
    {
        $membership->approve('member');
        $org = $membership->Organization();
        if ($org && $org->exists() && !$membership->Roles()->exists() && ($defaultRole = $org->getDefaultRole())) {
            $membership->Roles()->add($defaultRole);
        }
    }

    /**
     * POST /api/v1/organizations/invite/$OrgID { Username } — Person über ihren Benutzernamen
     * einladen (ORG_MANAGE_MEMBERS). Sie sieht die Einladung auf dem Dashboard und nimmt sie
     * dort an oder lehnt ab. Hat sie sich bereits beworben, wird sie direkt aufgenommen.
     */
    public function invite(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $org = Organization::get()->byID((int) $request->param('ID'));
        if (!$org || !$org->exists()) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }
        if (!$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $username = ltrim(trim((string) ($this->getJsonBody()['Username'] ?? '')), '@');
        if ($username === '') {
            return $this->errorResponse('Bitte einen Benutzernamen angeben', 400);
        }
        $invitee = Member::get()->filter('Username', $username)->first();
        if (!$invitee || !$invitee->exists()) {
            return $this->errorResponse('Es gibt niemanden mit dem Benutzernamen „' . $username . '“', 404);
        }

        $existing = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'MemberID'       => $invitee->ID,
        ])->first();
        if ($existing) {
            if ($existing->isInvited()) {
                return $this->errorResponse($invitee->getDisplayName() . ' ist bereits eingeladen', 409);
            }
            if ($existing->isApplicant()) {
                // Hat sich schon beworben — Einladen heißt dann: aufnehmen
                $existing->InvitedByID = $member->ID;
                $this->acceptInvitation($existing);
                return $this->successResponse(['accepted' => true], $invitee->getDisplayName() . ' hatte sich beworben und ist jetzt Mitglied');
            }
            return $this->errorResponse($invitee->getDisplayName() . ' ist bereits Mitglied', 409);
        }

        $membership = OrganizationMembership::create();
        $membership->OrganizationID = $org->ID;
        $membership->MemberID = $invitee->ID;
        $membership->Role = 'invited';
        $membership->InvitedByID = $member->ID;
        $membership->write();

        PushNotificationService::notifyInvitation($membership);

        return $this->successResponse(['accepted' => false], $invitee->getDisplayName() . ' wurde eingeladen');
    }

    /**
     * GET /api/v1/organizations/inviteSearch/$OrgID?q= — Vorschläge beim Einladen: Personen
     * mit Benutzernamen, deren Benutzer-, Vor- oder Nachname mit q beginnt, ohne Mitglieder
     * und bereits Eingeladene (Bewerber bleiben drin — Einladen nimmt sie direkt auf).
     */
    public function inviteSearch(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $org = Organization::get()->byID((int) $request->param('ID'));
        if (!$org || !$org->exists()) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }
        if (!$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $q = ltrim(trim((string) $request->getVar('q')), '@');
        if ($q === '') {
            return $this->jsonResponse(['members' => []]);
        }

        $memberships = OrganizationMembership::get()->filter('OrganizationID', $org->ID);
        $excluded = $memberships->filter('Role', ['member', 'invited'])->column('MemberID');
        $applicantIDs = array_flip(array_map('intval', $memberships->filter('Role', 'applicant')->column('MemberID')));

        $matches = Member::get()
            ->exclude('Username', [null, ''])
            ->exclude('ID', $excluded ?: [0])
            ->filterAny([
                'Username:StartsWith'  => $q,
                'FirstName:StartsWith' => $q,
                'Surname:StartsWith'   => $q,
            ])
            ->sort('Username', 'ASC')
            ->limit(8);

        $data = [];
        foreach ($matches as $m) {
            $data[] = [
                'ID'          => $m->ID,
                'Name'        => $m->getDisplayName(),
                'Username'    => $m->Username,
                'Avatar'      => $m->RenderProfileImage(),
                'IsApplicant' => isset($applicantIDs[(int) $m->ID]),
            ];
        }
        return $this->jsonResponse(['members' => $data]);
    }

    /**
     * POST /api/v1/organizations/removeMember/$MembershipID — Mitglied aus der Organisation
     * entfernen (ORG_MANAGE_MEMBERS). Nicht sich selbst; Administratoren nur durch einen
     * Administrator — so bleibt immer mindestens ein Administrator übrig.
     */
    public function removeMember(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $membership = OrganizationMembership::get()->byID((int) $request->param('ID'));
        if (!$membership || $membership->Role !== 'member') {
            return $this->errorResponse('Mitglied nicht gefunden', 404);
        }
        $org = $membership->Organization();
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }
        if ((int) $membership->MemberID === (int) $member->ID) {
            return $this->errorResponse('Du kannst dich nicht selbst entfernen', 400);
        }

        $removed = $membership->Member();
        if ($removed && $removed->exists() && $removed->isAdminOfOrg($org) && !$member->isAdminOfOrg($org)) {
            return $this->errorResponse('Administratoren können nur von Administratoren entfernt werden', 403);
        }

        $name = $removed && $removed->exists() ? $removed->getDisplayName() : 'Das Mitglied';
        $membership->Roles()->removeAll();
        $membership->delete();

        if ($removed && $removed->exists()) {
            PushNotificationService::notifyMembershipEnded($removed, $org);
        }

        return $this->successResponse([], $name . ' wurde aus „' . $org->Title . '“ entfernt');
    }

    /**
     * POST /api/v1/organizations/leave/$OrgID — Organisation selbst verlassen bzw. eine
     * Bewerbung zurückziehen. Der letzte Administrator kann nicht gehen (sonst könnte
     * niemand mehr die Organisation verwalten) — erst jemand anderem die Rolle geben.
     */
    public function leave(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $org = Organization::get()->byID((int) $request->param('ID'));
        if (!$org || !$org->exists()) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }
        $membership = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'MemberID'       => $member->ID,
        ])->first();
        if (!$membership || $membership->isInvited()) {
            return $this->errorResponse('Du bist kein Mitglied dieser Organisation', 404);
        }

        $wasApplicant = $membership->isApplicant();
        if (!$wasApplicant && $member->isAdminOfOrg($org)) {
            $otherAdmin = false;
            $others = OrganizationMembership::get()
                ->filter(['OrganizationID' => $org->ID, 'Role' => 'member'])
                ->exclude('MemberID', $member->ID);
            foreach ($others as $other) {
                $m = $other->Member();
                if ($m && $m->exists() && $m->isAdminOfOrg($org)) {
                    $otherAdmin = true;
                    break;
                }
            }
            if (!$otherAdmin) {
                return $this->errorResponse(
                    'Du bist der letzte Administrator. Gib zuerst einem anderen Mitglied die Administrator-Rolle, bevor du die Organisation verlässt.',
                    400
                );
            }
        }

        $membership->Roles()->removeAll();
        $membership->delete();

        return $this->successResponse(
            ['MembershipStatus' => null],
            $wasApplicant ? 'Bewerbung zurückgezogen' : 'Du hast „' . $org->Title . '“ verlassen'
        );
    }

    /** POST /api/v1/organizations/cancelInvitation/$MembershipID — offene Einladung zurückziehen */
    public function cancelInvitation(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $membership = OrganizationMembership::get()->byID((int) $request->param('ID'));
        if (!$membership || !$membership->isInvited()) {
            return $this->errorResponse('Einladung nicht gefunden', 404);
        }
        $org = $membership->Organization();
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $membership->delete();
        return $this->successResponse([], 'Einladung zurückgezogen');
    }

    /** GET /api/v1/organizations/invitations — offene Einladungen an mich (Dashboard) */
    public function invitations(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $data = [];
        foreach (OrganizationMembership::get()->filter(['MemberID' => $member->ID, 'Role' => 'invited'])->sort('Created DESC') as $inv) {
            $org = $inv->Organization();
            if (!$org || !$org->exists()) {
                continue;
            }
            $by = $inv->InvitedBy();
            $data[] = [
                'MembershipID' => $inv->ID,
                'Organization' => [
                    'ID'          => $org->ID,
                    'Title'       => $org->Title,
                    'Username'    => $org->Username ?: null,
                    'Description' => $org->Description,
                    'LogoURL'     => $org->RenderLogo(80),
                ],
                'InvitedBy'    => $by && $by->exists() ? [
                    'Name'   => $by->getDisplayName(),
                    'Avatar' => $by->RenderProfileImage(),
                ] : null,
                'Created'      => $inv->Created,
            ];
        }

        return $this->jsonResponse(['invitations' => $data]);
    }

    /** POST /api/v1/organizations/respondInvitation/$MembershipID { Accept: bool } */
    public function respondInvitation(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $membership = OrganizationMembership::get()->byID((int) $request->param('ID'));
        if (!$membership || !$membership->isInvited() || (int) $membership->MemberID !== (int) $member->ID) {
            return $this->errorResponse('Einladung nicht gefunden', 404);
        }

        if (!empty($this->getJsonBody()['Accept'])) {
            $this->acceptInvitation($membership);
            $org = $membership->Organization();
            return $this->successResponse(
                ['MembershipStatus' => 'member'],
                $org && $org->exists() ? 'Willkommen in „' . $org->Title . '“' : 'Einladung angenommen'
            );
        }

        $membership->delete();
        return $this->successResponse(['MembershipStatus' => null], 'Einladung abgelehnt');
    }

    /**
     * GET /api/v1/organizations/pendingCount — Zahl für das Hauptmenü: offene
     * Bewerbungen in allen eigenen Organisationen, in denen man Mitglieder annehmen darf
     */
    public function pendingCount(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = [];
        foreach (Organization::get()->filter('ID', $member->getOrganizationIDs() ?: [0]) as $org) {
            if ($member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
                $orgIDs[] = $org->ID;
            }
        }
        $count = $orgIDs
            ? OrganizationMembership::get()->filter(['OrganizationID' => $orgIDs, 'Role' => 'applicant'])->count()
            : 0;

        return $this->jsonResponse(['pendingApplicants' => $count]);
    }

    public function applicants(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgID = (int) $request->param('ID');
        $org   = Organization::get()->byID($orgID);
        if (!$org) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        if (!$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $data = [];
        $applicants = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'Role'           => 'applicant',
        ]);

        foreach ($applicants as $applicantMembership) {
            $m = $applicantMembership->Member();
            if (!$m) {
                continue;
            }
            $data[] = [
                'MembershipID' => $applicantMembership->ID,
                'MemberID'     => $m->ID,
                'FirstName'    => $m->FirstName,
                'Surname'      => $m->Surname,
                'Email'        => $m->Email,
                'Avatar'       => $m->RenderProfileImage(),
            ];
        }

        return $this->jsonResponse(['applicants' => $data]);
    }

    public function uploadLogo(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $orgID = (int) $request->param('ID');
        $org   = Organization::get()->byID($orgID);
        if (!$org) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        if (!$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_SETTINGS)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->errorResponse('Keine Datei hochgeladen');
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return $this->errorResponse('Die Datei darf maximal 2 MB groß sein');
        }

        // Der Cropper im Frontend liefert das Ergebnis immer als JPEG (die Beschneidung
        // auf 180×180 passiert bereits client-seitig per Canvas), daher wird hier bewusst
        // nur JPEG akzeptiert statt beliebiger Bildformate.
        $mime = mime_content_type($file['tmp_name']);
        if ($mime !== 'image/jpeg') {
            return $this->errorResponse('Nur JPEG wird akzeptiert');
        }

        // Delete old logo before saving new one
        if ($org->LogoID && $org->Logo()->exists()) {
            $oldImage = $org->Logo();
            $oldImage->deleteFile();
            $oldImage->delete();
        }

        $folder = 'OrganizationImages/' . ($org->Username ?: $org->ID);

        $image  = Image::create();
        $upload = Upload::create();
        $upload->getValidator()->setAllowedExtensions(['jpg', 'jpeg']);
        $upload->getValidator()->setAllowedMaxFileSize(2 * 1024 * 1024);

        $result = $upload->loadIntoFile([
            'name'     => 'OrganizationIcon.jpg',
            'type'     => $mime,
            'tmp_name' => $file['tmp_name'],
            'error'    => UPLOAD_ERR_OK,
            'size'     => $file['size'],
        ], $image, $folder);

        if (!$result) {
            $errors = $upload->getErrors();
            return $this->errorResponse(
                !empty($errors) ? implode(', ', $errors) : 'Bild konnte nicht gespeichert werden'
            );
        }

        $image->write();
        $image->publishSingle();

        $org->LogoID = $image->ID;
        $org->write();

        return $this->successResponse([
            'LogoURL' => $org->RenderLogo(200),
        ], 'Logo gespeichert');
    }

    public function accept(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $membershipID = (int) $request->param('ID');
        $membership   = OrganizationMembership::get()->byID($membershipID);

        if (!$membership || $membership->Role !== 'applicant') {
            return $this->errorResponse('Bewerbung nicht gefunden', 404);
        }

        $org = $membership->Organization();
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $membership->approve('member');
        if ($defaultRole = $org->getDefaultRole()) {
            $membership->Roles()->add($defaultRole);
        }

        return $this->successResponse([], 'Bewerbung angenommen');
    }

    public function reject(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $membershipID = (int) $request->param('ID');
        $membership   = OrganizationMembership::get()->byID($membershipID);

        if (!$membership || $membership->Role !== 'applicant') {
            return $this->errorResponse('Bewerbung nicht gefunden', 404);
        }

        $org = $membership->Organization();
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $membership->delete();

        return $this->successResponse([], 'Bewerbung abgelehnt');
    }
}
