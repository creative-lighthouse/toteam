<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Teams\OrganizationMembership;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\ProfileApiController
 *
 */
class ProfileApiController extends ApiController
{
    private static $url_segment = 'api/v1/profile';

    private static $allowed_actions = [
        'index',
        'update',
        'uploadImage',
        'leaveOrg',
        'user',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        return $this->jsonResponse([
            'success' => true,
            'profile' => $this->serializeProfile($member),
        ]);
    }

    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();

        if (isset($data['FirstName'])) {
            $member->FirstName = trim($data['FirstName']);
        }
        if (isset($data['Surname'])) {
            $member->Surname = trim($data['Surname']);
        }
        if (isset($data['Email'])) {
            $member->Email = trim($data['Email']);
        }
        if (isset($data['FoodPreference']) && in_array($data['FoodPreference'], ['None', 'Vegetarian', 'Vegan'], true)) {
            $member->FoodPreference = $data['FoodPreference'];
        }
        if (isset($data['NameVisibility']) && in_array($data['NameVisibility'], ['full', 'first', 'username'], true)) {
            $member->NameVisibility = $data['NameVisibility'];
        }

        $member->write();

        return $this->successResponse($this->serializeProfile($member), 'Profil aktualisiert');
    }

    public function uploadImage(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->errorResponse('Keine Datei hochgeladen');
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return $this->errorResponse('Die Datei darf maximal 2 MB groß sein');
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return $this->errorResponse('Nur PNG und JPEG sind erlaubt');
        }

        // Delete old profile image before saving new one
        if ($member->ProfileImageID && $member->ProfileImage()->exists()) {
            $oldImage = $member->ProfileImage();
            $oldImage->deleteFile();
            $oldImage->delete();
        }

        $ext      = ($mime === 'image/png') ? 'png' : 'jpg';
        $slug     = preg_replace('/[^a-z0-9]+/', '-', strtolower($member->Email));
        $filename = trim($slug, '-') . '.' . $ext;

        $image  = Image::create();
        $upload = Upload::create();
        $upload->getValidator()->setAllowedExtensions(['jpg', 'jpeg', 'png']);
        $upload->getValidator()->setAllowedMaxFileSize(2 * 1024 * 1024);

        $result = $upload->loadIntoFile([
            'name'     => $filename,
            'type'     => $mime,
            'tmp_name' => $file['tmp_name'],
            'error'    => UPLOAD_ERR_OK,
            'size'     => $file['size'],
        ], $image, 'ProfileImages');

        if (!$result) {
            $errors = $upload->getErrors();
            return $this->errorResponse(
                !empty($errors) ? implode(', ', $errors) : 'Bild konnte nicht gespeichert werden'
            );
        }

        $image->write();
        $image->publishSingle();

        $member->ProfileImageID = $image->ID;
        $member->write();

        return $this->successResponse([
            'ProfileImage' => [
                'URL' => $image->FillMax(400, 400)->getURL(),
            ],
        ], 'Profilbild gespeichert');
    }

    public function user(HTTPRequest $request): HTTPResponse
    {
        if (!$this->requireAuth()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $username = trim($request->param('ID') ?? '');
        if (!$username) {
            return $this->errorResponse('Benutzername fehlt', 400);
        }

        $member = Member::get()->filter('Username', $username)->first();
        if (!$member) {
            return $this->errorResponse('Profil nicht gefunden', 404);
        }

        return $this->jsonResponse([
            'success' => true,
            'profile' => $this->serializePublicProfile($member),
        ]);
    }

    public function leaveOrg(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $membershipID = (int) $request->param('ID');
        $membership   = OrganizationMembership::get()->filter([
            'ID'       => $membershipID,
            'MemberID' => $member->ID,
        ])->first();

        if (!$membership) {
            return $this->errorResponse('Mitgliedschaft nicht gefunden', 404);
        }

        $membership->delete();

        return $this->successResponse([], 'Mitgliedschaft aufgelöst');
    }

    private function serializePublicProfile(Member $member): array
    {
        $orgs = [];
        foreach ($member->OrganizationMemberships() as $ms) {
            $org = $ms->Organization();
            if (!$org || !$org->exists()) {
                continue;
            }
            $orgs[] = [
                'MembershipID' => $ms->ID,
                'OrgID'        => $org->ID,
                'Title'        => $org->Title,
                'Username'     => $org->Username ?: null,
                'Role'         => $ms->Role,
                'LogoURL'      => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(60)->getURL() : null,
            ];
        }

        $visibility = $member->NameVisibility ?: 'full';

        return [
            'FirstName'      => in_array($visibility, ['full', 'first']) ? $member->FirstName : null,
            'Surname'        => $visibility === 'full' ? $member->Surname : null,
            'Username'       => $member->Username ?: null,
            'NameVisibility' => $visibility,
            'FoodPreference' => $member->FoodPreference ?: 'None',
            'DateOfBirth'    => $member->DateOfBirth,
            'Joindate'       => $member->Joindate,
            'Gravatar'       => $member->getGravatar(),
            'ProfileImage'   => ($member->ProfileImageID && $member->ProfileImage()->exists())
                ? ['URL' => $member->ProfileImage()->FillMax(400, 400)->getURL()]
                : null,
            'Organizations'  => $orgs,
        ];
    }

    private function serializeProfile(Member $member): array
    {
        $orgs = [];
        foreach ($member->OrganizationMemberships() as $ms) {
            $org = $ms->Organization();
            if (!$org || !$org->exists()) {
                continue;
            }
            $orgs[] = [
                'MembershipID' => $ms->ID,
                'OrgID'        => $org->ID,
                'Title'        => $org->Title,
                'Username'     => $org->Username ?: null,
                'Role'         => $ms->Role,
                'JoinedDate'   => $ms->JoinedDate,
                'LogoURL'      => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(60)->getURL() : null,
            ];
        }

        return [
            'ID'             => $member->ID,
            'FirstName'      => $member->FirstName,
            'Surname'        => $member->Surname,
            'Email'          => $member->Email,
            'Username'        => $member->Username ?: null,
            'NameVisibility'  => $member->NameVisibility ?: 'full',
            'FoodPreference'  => $member->FoodPreference ?: 'None',
            'DateOfBirth'    => $member->DateOfBirth,
            'Joindate'       => $member->Joindate,
            'Gravatar'       => $member->getGravatar(),
            'ProfileImage'   => ($member->ProfileImageID && $member->ProfileImage()->exists())
                ? ['URL' => $member->ProfileImage()->FillMax(400, 400)->getURL()]
                : null,
            'Organizations'  => $orgs,
        ];
    }
}
