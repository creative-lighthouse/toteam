<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
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
        'join',
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

            $data[] = [
                'ID'               => $org->ID,
                'Title'            => $org->Title,
                'Description'      => $org->Description,
                'LogoURL'          => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(80)->getURL() : null,
                'CoverURL'         => $org->CoverImage()->exists() ? $org->CoverImage()->ScaleWidth(600)->getURL() : null,
                'JoinMode'         => $org->JoinMode,
                'MemberCount'      => $memberCount,
                'MembershipStatus' => $membership ? $membership->Role : null,
            ];
        }

        return $this->jsonResponse(['organizations' => $data]);
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

        return $this->successResponse([
            'MembershipStatus' => $role,
        ], $role === 'member' ? 'Erfolgreich beigetreten' : 'Bewerbung erfolgreich eingereicht');
    }
}
