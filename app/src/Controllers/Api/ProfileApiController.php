<?php

namespace App\Controllers\Api;

use App\Auth\LoginCode;
use App\Auth\LoginCodeMailer;
use App\Controllers\ApiController;
use App\HumanResources\Allergy;
use App\Teams\OrganizationMembership;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;

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
        'requestEmailChange',
        'confirmEmailChange',
        'setPassword',
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
        // Email is intentionally NOT settable here anymore — since login is
        // passwordless (email + one-time code), the email address IS the
        // credential, so changing it goes through requestEmailChange()/
        // confirmEmailChange() below instead of being written directly.
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
                    'category' => $allergy->Category,
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

    /**
     * Step 1 of changing the profile email: sends a code to the NEW address.
     * The current email is left untouched until confirmEmailChange() succeeds.
     */
    public function requestEmailChange(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();
        $email = strtolower(trim($data['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Bitte gib eine gültige E-Mail-Adresse ein.');
        }

        if (Member::get()->filter('Email', $email)->exclude('ID', $member->ID)->exists()) {
            return $this->errorResponse('Diese E-Mail-Adresse wird bereits verwendet.');
        }

        if (!LoginCode::canRequest($email, LoginCode::PURPOSE_EMAIL_CHANGE)) {
            return $this->errorResponse('Bitte warte kurz, bevor du einen neuen Code anforderst.', 429);
        }

        [, $code] = LoginCode::issue($email, LoginCode::PURPOSE_EMAIL_CHANGE, $member->ID);
        LoginCodeMailer::sendEmailChangeCode($email, $code);

        return $this->successResponse([], 'Code wurde an die neue Adresse verschickt.');
    }

    /**
     * Step 2: verifying the code actually applies the new email.
     */
    public function confirmEmailChange(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();
        $email = strtolower(trim($data['email'] ?? ''));
        $code = trim($data['code'] ?? '');

        if (!$email || !$code) {
            return $this->errorResponse('E-Mail und Code sind erforderlich.');
        }

        $entry = LoginCode::verify($email, LoginCode::PURPOSE_EMAIL_CHANGE, $code);
        if (!$entry || (int) $entry->MemberID !== (int) $member->ID) {
            return $this->errorResponse('Der Code ist ungültig oder abgelaufen.', 401);
        }

        // Re-check uniqueness — the address may have been taken by someone
        // else between requesting and confirming the code.
        if (Member::get()->filter('Email', $email)->exclude('ID', $member->ID)->exists()) {
            return $this->errorResponse('Diese E-Mail-Adresse wird bereits verwendet.');
        }

        $entry->consume();

        $member->Email = $email;
        $member->write();

        return $this->successResponse($this->serializeProfile($member), 'E-Mail-Adresse aktualisiert.');
    }

    /**
     * Sets or changes the member's password, opting them into email+password
     * as an alternative to the code login. If a password is already set,
     * the current one must be confirmed first.
     */
    public function setPassword(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        if (!$newPassword) {
            return $this->errorResponse('Bitte gib ein neues Passwort ein.');
        }

        if ($member->PasswordSet) {
            if (!$currentPassword) {
                return $this->errorResponse('Bitte gib dein aktuelles Passwort ein.');
            }
            $authenticator = new MemberAuthenticator();
            $verified = $authenticator->authenticate(['Email' => $member->Email, 'Password' => $currentPassword], $request);
            if (!$verified || (int) $verified->ID !== (int) $member->ID) {
                return $this->errorResponse('Das aktuelle Passwort ist falsch.', 401);
            }
        }

        $result = $member->changePassword($newPassword);
        if (!$result->isValid()) {
            $messages = array_map(fn($m) => $m['message'], $result->getMessages());
            return $this->errorResponse($messages ? implode(' ', $messages) : 'Passwort ungültig.');
        }

        $member->PasswordSet = true;
        $member->write();

        return $this->successResponse([], 'Passwort gespeichert.');
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
            'HasPassword'    => (bool) $member->PasswordSet,
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
