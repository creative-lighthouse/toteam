<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\HumanResources\Allergy;
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
        'allergies',
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

        // Der Cropper im Frontend liefert das Ergebnis immer als JPEG (die Beschneidung
        // auf 180×180 passiert bereits client-seitig per Canvas), daher wird hier bewusst
        // nur JPEG akzeptiert statt beliebiger Bildformate.
        $mime = mime_content_type($file['tmp_name']);
        if ($mime !== 'image/jpeg') {
            return $this->errorResponse('Nur JPEG wird akzeptiert');
        }

        // Delete old profile image before saving new one
        if ($member->ProfileImageID && $member->ProfileImage()->exists()) {
            $oldImage = $member->ProfileImage();
            $oldImage->deleteFile();
            $oldImage->delete();
        }

        $folder = 'ProfileImages/' . ($member->Username ?: $member->ID);

        $image  = Image::create();
        $upload = Upload::create();
        $upload->getValidator()->setAllowedExtensions(['jpg', 'jpeg']);
        $upload->getValidator()->setAllowedMaxFileSize(2 * 1024 * 1024);

        $result = $upload->loadIntoFile([
            'name'     => 'ProfileImage.jpg',
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

        $member->ProfileImageID = $image->ID;
        $member->write();

        return $this->successResponse([
            'Avatar' => $member->RenderProfileImage(),
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

    public function allergies(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() === 'GET') {
            $memberIds = $member->Allergies()->column('ID');
            $result    = [];
            foreach (Allergy::get()->sort('Title ASC') as $allergy) {
                $result[] = [
                    'id'       => $allergy->ID,
                    'title'    => $allergy->Title,
                    'selected' => in_array($allergy->ID, $memberIds),
                ];
            }
            return $this->jsonResponse(['allergies' => $result]);
        }

        if ($request->httpMethod() === 'PUT') {
            $body = $this->getJsonBody();
            $ids  = array_map('intval', $body['allergyIds'] ?? []);
            $member->Allergies()->setByIDList($ids);
            return $this->successResponse([], 'Allergien gespeichert');
        }

        return $this->errorResponse('Method not allowed', 405);
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
                'LogoURL'      => $org->RenderLogo(60),
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
            'Avatar'         => $member->RenderProfileImage(),
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
                'LogoURL'      => $org->RenderLogo(60),
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
            'Avatar'         => $member->RenderProfileImage(),
            'Organizations'  => $orgs,
        ];
    }
}
