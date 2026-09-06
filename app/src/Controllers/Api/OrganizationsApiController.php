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
        'uploadLogo',
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
            ];
        }
        usort($members, fn($a, $b) => strcasecmp($a['Name'], $b['Name']));

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
                'CanManageMembers' => $canManageMembers,
                'CanManageRoles'   => $canManageRoles,
                'Permissions'      => $member->getOrgPermissionCodes($org),
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
