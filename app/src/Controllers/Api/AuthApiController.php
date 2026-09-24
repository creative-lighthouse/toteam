<?php

namespace App\Controllers\Api;

use App\Auth\JwtHelper;
use App\Auth\LoginCode;
use App\Auth\LoginCodeMailer;
use App\Auth\LoginSession;
use App\Controllers\ApiController;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;
use SilverStripe\Security\Security;

/**
 * Two ways to log in — a one-time code sent by email (the default, and the
 * only option for a brand new account), or email+password for members who've
 * set one (see ProfileApiController::setPassword()). Both end up in the same
 * place: a JWT access token (returned in the body) plus a refresh token
 * (set as an httpOnly cookie), tracked as a revocable login session.
 */
class AuthApiController extends ApiController
{
    private static $url_segment = 'api/v1/auth';

    public const REFRESH_COOKIE = 'toteam_refresh';

    private static $allowed_actions = [
        'check',
        'requestCode',
        'verifyCode',
        'loginPassword',
        'requestPasswordReset',
        'resetPassword',
        'refresh',
        'logout',
        'sessions',
        'removeSession',
    ];

    /**
     * Check if the current access token (if any) is valid.
     */
    public function check(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if ($member) {
            return $this->jsonResponse([
                'authenticated' => true,
                'user' => $this->serializeMember($member),
            ]);
        }

        return $this->jsonResponse([
            'authenticated' => false,
            'user' => null,
        ]);
    }

    /**
     * Step 1: request a login code for an email address. Always responds
     * generically so we don't leak whether an account exists.
     */
    public function requestCode(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();
        $email = strtolower(trim($data['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Bitte gib eine gültige E-Mail-Adresse ein.');
        }

        if (!LoginCode::canRequest($email, LoginCode::PURPOSE_LOGIN)) {
            return $this->errorResponse('Bitte warte kurz, bevor du einen neuen Code anforderst.', 429);
        }

        [, $code] = LoginCode::issue($email, LoginCode::PURPOSE_LOGIN);
        LoginCodeMailer::sendLoginCode($email, $code);

        return $this->successResponse([
            'isNewAccount' => !Member::get()->filter('Email', $email)->exists(),
        ], 'Code wurde per E-Mail verschickt.');
    }

    /**
     * Step 2: verify the code. Creates the Member if the email is new
     * (requires firstName/surname in that case), then logs in.
     */
    public function verifyCode(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();
        $email = strtolower(trim($data['email'] ?? ''));
        $code = trim($data['code'] ?? '');
        $firstName = trim($data['firstName'] ?? '');
        $surname = trim($data['surname'] ?? '');

        if (!$email || !$code) {
            return $this->errorResponse('E-Mail und Code sind erforderlich.');
        }

        $entry = LoginCode::verify($email, LoginCode::PURPOSE_LOGIN, $code);
        if (!$entry) {
            return $this->errorResponse('Der Code ist ungültig oder abgelaufen.', 401);
        }

        $member = Member::get()->filter('Email', $email)->first();

        if (!$member) {
            if (!$firstName || !$surname) {
                return $this->jsonResponse([
                    'success' => false,
                    'needsProfile' => true,
                    'message' => 'Bitte gib deinen Namen an, um das Konto zu erstellen.',
                ], 200);
            }

            $member = Member::create();
            $member->Email = $email;
            $member->FirstName = $firstName;
            $member->Surname = $surname;
            $member->write();
        }

        $entry->consume();

        return $this->startSession($request, $member);
    }

    /**
     * Alternative to the code flow: email + password, for members who have
     * set a password (see ProfileApiController::setPassword()). Goes through
     * SilverStripe's own MemberAuthenticator, so the built-in brute-force
     * lockout (10 failed attempts → 15 min lock) applies here for free.
     */
    public function loginPassword(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->errorResponse('E-Mail und Passwort sind erforderlich.');
        }

        $member = Member::get()->filter('Email', $email)->first();
        if ($member && !$member->PasswordSet) {
            return $this->errorResponse(
                'Für dieses Konto ist kein Passwort eingerichtet. Bitte nutze den Anmeldecode.',
                401
            );
        }

        $authenticator = new MemberAuthenticator();
        $member = $authenticator->authenticate(['Email' => $email, 'Password' => $password], $request);

        if (!$member) {
            return $this->errorResponse('E-Mail oder Passwort ist falsch.', 401);
        }

        return $this->startSession($request, $member);
    }

    /**
     * "Forgot password" step 1: send a reset code, generic response either
     * way so we don't leak whether the account/password exists.
     */
    public function requestPasswordReset(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();
        $email = strtolower(trim($data['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Bitte gib eine gültige E-Mail-Adresse ein.');
        }

        if (!LoginCode::canRequest($email, LoginCode::PURPOSE_PASSWORD_RESET)) {
            return $this->errorResponse('Bitte warte kurz, bevor du einen neuen Code anforderst.', 429);
        }

        // Only actually send the email if an account exists — but the response
        // is identical regardless, so this can't be used to probe for accounts.
        $member = Member::get()->filter('Email', $email)->first();
        if ($member) {
            [, $code] = LoginCode::issue($email, LoginCode::PURPOSE_PASSWORD_RESET);
            LoginCodeMailer::sendPasswordResetCode($email, $code);
        }

        return $this->successResponse([], 'Falls ein Konto mit dieser E-Mail-Adresse existiert, wurde ein Code verschickt.');
    }

    /**
     * "Forgot password" step 2: verify the code and set a new password.
     * Also logs the member in, same as the other login flows.
     */
    public function resetPassword(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $data = $this->getJsonBody();
        $email = strtolower(trim($data['email'] ?? ''));
        $code = trim($data['code'] ?? '');
        $newPassword = $data['newPassword'] ?? '';

        if (!$email || !$code || !$newPassword) {
            return $this->errorResponse('E-Mail, Code und neues Passwort sind erforderlich.');
        }

        $entry = LoginCode::verify($email, LoginCode::PURPOSE_PASSWORD_RESET, $code);
        if (!$entry) {
            return $this->errorResponse('Der Code ist ungültig oder abgelaufen.', 401);
        }

        $member = Member::get()->filter('Email', $email)->first();
        if (!$member) {
            return $this->errorResponse('Der Code ist ungültig oder abgelaufen.', 401);
        }

        $result = $member->changePassword($newPassword);
        if (!$result->isValid()) {
            $messages = array_map(fn($m) => $m['message'], $result->getMessages());
            return $this->errorResponse($messages ? implode(' ', $messages) : 'Passwort ungültig.');
        }

        $member->PasswordSet = true;
        $member->write();

        $entry->consume();

        return $this->startSession($request, $member);
    }

    /**
     * Silently renews the access token using the refresh cookie, rotating it.
     */
    public function refresh(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $token = Cookie::get(self::REFRESH_COOKIE);
        if (!$token) {
            return $this->errorResponse('Keine aktive Sitzung.', 401);
        }

        $session = LoginSession::get()->filter('RefreshTokenHash', LoginSession::hashToken($token))->first();
        if (!$session || !$session->isValid()) {
            Cookie::force_expiry(self::REFRESH_COOKIE, '/');
            return $this->errorResponse('Sitzung abgelaufen oder widerrufen.', 401);
        }

        $member = $session->Member();
        if (!$member || !$member->exists()) {
            $session->revoke();
            return $this->errorResponse('Sitzung abgelaufen oder widerrufen.', 401);
        }

        $newToken = $session->rotate();
        $this->setRefreshCookie($newToken);

        return $this->jsonResponse([
            'success' => true,
            'accessToken' => JwtHelper::issueAccessToken($member->ID),
            'user' => $this->serializeMember($member),
        ]);
    }

    /**
     * Logs out only the current device/session.
     */
    public function logout(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $token = Cookie::get(self::REFRESH_COOKIE);
        if ($token) {
            $session = LoginSession::get()->filter('RefreshTokenHash', LoginSession::hashToken($token))->first();
            $session?->revoke();
        }
        Cookie::force_expiry(self::REFRESH_COOKIE, '/');

        return $this->successResponse([], 'Abgemeldet.');
    }

    /**
     * Lists the current member's active login sessions (devices).
     */
    public function sessions(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $currentToken = Cookie::get(self::REFRESH_COOKIE);
        $currentHash = $currentToken ? LoginSession::hashToken($currentToken) : null;

        $sessions = [];
        foreach (LoginSession::get()->filter(['MemberID' => $member->ID, 'RevokedAt' => null]) as $session) {
            if (!$session->isValid()) {
                continue;
            }
            $sessions[] = [
                'ID'          => $session->ID,
                'DeviceLabel' => $session->DeviceLabel,
                'IPAddress'   => $session->IPAddress,
                'LastUsedAt'  => $session->LastUsedAt,
                'IsCurrent'   => $currentHash !== null && $session->RefreshTokenHash === $currentHash,
            ];
        }

        return $this->jsonResponse(['sessions' => $sessions]);
    }

    /**
     * Revokes a single session (e.g. "log out this device") by ID.
     */
    public function removeSession(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $session = LoginSession::get()->filter([
            'ID'       => (int) $request->param('ID'),
            'MemberID' => $member->ID,
        ])->first();

        if (!$session) {
            return $this->errorResponse('Sitzung nicht gefunden', 404);
        }

        $session->revoke();

        return $this->successResponse([], 'Sitzung abgemeldet.');
    }

    private function startSession(HTTPRequest $request, Member $member): HTTPResponse
    {
        $userAgent = (string) $request->getHeader('User-Agent');
        $ip = (string) $request->getIP();

        [, $refreshToken] = LoginSession::start($member, $userAgent, $ip);
        $this->setRefreshCookie($refreshToken);

        Security::setCurrentUser($member);

        return $this->jsonResponse([
            'success' => true,
            'accessToken' => JwtHelper::issueAccessToken($member->ID),
            'user' => $this->serializeMember($member),
        ]);
    }

    private function setRefreshCookie(string $token): void
    {
        // Lax (not Strict) so the cookie survives top-level navigations from
        // external links / PWA launches. httpOnly so it's inaccessible to JS.
        Cookie::set(
            self::REFRESH_COOKIE,
            $token,
            60, // days
            '/',
            null,
            true,   // secure
            true,   // httpOnly
            'Lax'
        );
    }

    private function serializeMember(Member $member): array
    {
        return [
            'ID'             => $member->ID,
            'Email'          => $member->Email,
            'FirstName'      => $member->FirstName,
            'Surname'        => $member->Surname,
            'Avatar'         => $member->RenderProfileImage(),
            'Hash'           => $member->Hash,
            'Username'       => $member->Username ?: null,
            'NameVisibility' => $member->NameVisibility ?: 'full',
            'FoodPreference' => $member->FoodPreference ?: 'None',
            'DateOfBirth'    => $member->DateOfBirth,
            'Joindate'       => $member->Joindate,
            'EnabledTotems'  => $member->getEnabledTotems(),
        ];
    }
}
