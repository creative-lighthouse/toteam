<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Notifications\PushNotificationService;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

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
        'join',
        'applicants',
        'accept',
        'reject',
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
                'Role'           => ['member', 'moderator', 'admin'],
            ])->count();

            $membership = OrganizationMembership::get()->filter([
                'OrganizationID' => $org->ID,
                'MemberID'       => $member->ID,
            ])->first();

            $myRole = $membership ? $membership->Role : null;

            $applicantCount = in_array($myRole, ['admin', 'moderator'])
                ? OrganizationMembership::get()->filter([
                    'OrganizationID' => $org->ID,
                    'Role'           => 'applicant',
                ])->count()
                : null;

            $data[] = [
                'ID'               => $org->ID,
                'Username'         => $org->Username ?: null,
                'Title'            => $org->Title,
                'Description'      => $org->Description,
                'LogoURL'          => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(80)->getURL() : null,
                'CoverURL'         => $org->CoverImage()->exists() ? $org->CoverImage()->ScaleWidth(600)->getURL() : null,
                'JoinMode'         => $org->JoinMode,
                'MemberCount'      => $memberCount,
                'MembershipStatus' => $myRole,
                'ApplicantCount'   => $applicantCount,
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
            'Role'           => ['member', 'moderator', 'admin'],
        ])->count();

        $applicantCount = in_array($myRole, ['admin', 'moderator'])
            ? OrganizationMembership::get()->filter([
                'OrganizationID' => $org->ID,
                'Role'           => 'applicant',
            ])->count()
            : null;

        $memberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'Role'           => ['member', 'moderator', 'admin'],
        ]);

        $roleOrder = ['admin' => 0, 'moderator' => 1, 'member' => 2];
        $members = [];
        foreach ($memberships as $ms) {
            $m = $ms->Member();
            if (!$m) {
                continue;
            }
            $members[] = [
                'MemberID'  => $m->ID,
                'Name'      => $m->getDisplayName(),
                'Avatar'    => $m->RenderProfileImage(),
                'Username'  => $m->Username ?: null,
                'Role'      => $ms->Role,
                'SortOrder' => $roleOrder[$ms->Role] ?? 3,
            ];
        }
        usort($members, fn($a, $b) => $a['SortOrder'] <=> $b['SortOrder']);
        foreach ($members as &$m) {
            unset($m['SortOrder']);
        }
        unset($m);

        return $this->jsonResponse([
            'organization' => [
                'ID'               => $org->ID,
                'Username'         => $org->Username ?: null,
                'Title'            => $org->Title,
                'Description'      => $org->Description,
                'LogoURL'          => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(200)->getURL() : null,
                'CoverURL'         => $org->CoverImage()->exists() ? $org->CoverImage()->ScaleWidth(1200)->getURL() : null,
                'JoinMode'         => $org->JoinMode,
                'MemberCount'      => $memberCount,
                'MembershipStatus' => $myRole,
                'ApplicantCount'   => $applicantCount,
                'Members'          => $members,
            ],
        ]);
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

        if ($org->JoinMode === 'invite_only') {
            return $this->errorResponse('Diese Organisation nimmt keine Bewerbungen an', 403);
        }

        $existing = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'MemberID'       => $member->ID,
        ])->first();

        if ($existing) {
            return $this->errorResponse('Du bist bereits Mitglied oder hast dich bereits beworben', 409);
        }

        $role = $org->JoinMode === 'open' ? 'member' : 'applicant';

        $membership = OrganizationMembership::create();
        $membership->OrganizationID = $org->ID;
        $membership->MemberID       = $member->ID;
        $membership->Role           = $role;
        $membership->write();

        if ($role === 'applicant') {
            PushNotificationService::notifyNewApplication($membership);
        }

        return $this->successResponse([
            'MembershipStatus' => $role,
        ], $role === 'member' ? 'Erfolgreich beigetreten' : 'Bewerbung erfolgreich eingereicht');
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

        $myMembership = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'MemberID'       => $member->ID,
            'Role'           => ['admin', 'moderator'],
        ])->first();

        if (!$myMembership) {
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
                'Gravatar'     => $m->getGravatar(),
            ];
        }

        return $this->jsonResponse(['applicants' => $data]);
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

        $myMembership = OrganizationMembership::get()->filter([
            'OrganizationID' => $membership->OrganizationID,
            'MemberID'       => $member->ID,
            'Role'           => ['admin', 'moderator'],
        ])->first();

        if (!$myMembership) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $membership->approve('member');

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

        $myMembership = OrganizationMembership::get()->filter([
            'OrganizationID' => $membership->OrganizationID,
            'MemberID'       => $member->ID,
            'Role'           => ['admin', 'moderator'],
        ])->first();

        if (!$myMembership) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $membership->delete();

        return $this->successResponse([], 'Bewerbung abgelehnt');
    }
}
