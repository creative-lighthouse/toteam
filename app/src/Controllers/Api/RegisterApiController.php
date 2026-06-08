<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\RegisterApiController
 *
 */
class RegisterApiController extends ApiController
{
    private static $url_segment = 'api/v1/register';

    private static $allowed_actions = [
        'index',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();

        $firstName = trim($data['FirstName'] ?? '');
        $surname   = trim($data['Surname'] ?? '');
        $email     = trim($data['Email'] ?? '');
        $password  = $data['Password'] ?? '';
        $passwordConfirm = $data['PasswordConfirm'] ?? '';

        // Validate
        if (!$firstName || !$surname || !$email || !$password) {
            return $this->errorResponse('Alle Felder sind erforderlich.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Ungültige E-Mail-Adresse.');
        }

        if (strlen($password) < 8) {
            return $this->errorResponse('Das Passwort muss mindestens 8 Zeichen lang sein.');
        }

        if ($password !== $passwordConfirm) {
            return $this->errorResponse('Die Passwörter stimmen nicht überein.');
        }

        if (Member::get()->filter('Email', $email)->exists()) {
            return $this->errorResponse('Diese E-Mail-Adresse ist bereits registriert.');
        }

        $member = Member::create();
        $member->FirstName = $firstName;
        $member->Surname   = $surname;
        $member->Email     = $email;
        $member->Password  = $password;
        $member->write();

        // Log in automatically
        $identityStore = Injector::inst()->get(IdentityStore::class);
        $identityStore->logIn($member, true, $request);

        return $this->jsonResponse([
            'success' => true,
            'user'    => [
                'ID'        => $member->ID,
                'Email'     => $member->Email,
                'FirstName' => $member->FirstName,
                'Surname'   => $member->Surname,
                'Gravatar'  => $member->getGravatar(),
            ],
            'message' => 'Registrierung erfolgreich.',
        ]);
    }
}
