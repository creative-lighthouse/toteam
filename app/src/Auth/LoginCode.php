<?php

namespace App\Auth;

use SilverStripe\ORM\DataObject;

/**
 * A one-time, 6-digit login/verification code sent to an email address.
 * Used both for passwordless login/signup and for confirming a member's new
 * email address before it takes effect.
 *
 * @property string $Email
 * @property string $CodeHash
 * @property string $Purpose
 * @property int $MemberID
 * @property int $Attempts
 * @property ?string $ExpiresAt
 * @property ?string $ConsumedAt
 * @property ?string $Created Inherited from DataObject, set automatically on first write.
 */
class LoginCode extends DataObject
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_EMAIL_CHANGE = 'email_change';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    private const CODE_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const REQUEST_COOLDOWN_SECONDS = 60;
    private const MAX_REQUESTS_PER_HOUR = 5;

    private static $table_name = 'LoginCode';
    private static $singular_name = 'Login-Code';
    private static $plural_name = 'Login-Codes';

    private static $db = [
        'Email'         => 'Varchar(255)',
        'CodeHash'      => 'Varchar(255)',
        'Purpose'       => "Enum('login,email_change,password_reset', 'login')",
        // MemberID is only set for email_change (the account requesting the change);
        // login codes may be requested before an account even exists.
        'MemberID'      => 'Int',
        'Attempts'      => 'Int',
        'ExpiresAt'     => 'Datetime',
        'ConsumedAt'    => 'Datetime',
    ];

    private static $indexes = [
        'Email' => true,
    ];

    /**
     * Whether a new code may be requested for this email/purpose right now
     * (cooldown between requests + hourly cap against inbox-spam abuse).
     */
    public static function canRequest(string $email, string $purpose): bool
    {
        $recent = static::get()->filter([
            'Email'   => $email,
            'Purpose' => $purpose,
        ])->sort('Created DESC');

        $latest = $recent->first();
        if ($latest && $latest->Created && strtotime($latest->Created) > time() - self::REQUEST_COOLDOWN_SECONDS) {
            return false;
        }

        $sinceHour = (new \DateTime('-1 hour'))->format('Y-m-d H:i:s');
        $countLastHour = $recent->filter('Created:GreaterThan', $sinceHour)->count();

        return $countLastHour < self::MAX_REQUESTS_PER_HOUR;
    }

    /**
     * Creates a new code, invalidating any still-usable earlier codes for the
     * same email/purpose, and returns the plaintext 6-digit code.
     */
    public static function issue(string $email, string $purpose, ?int $memberID = null): array
    {
        foreach (static::get()->filter(['Email' => $email, 'Purpose' => $purpose, 'ConsumedAt' => null]) as $stale) {
            $stale->ConsumedAt = (new \DateTime())->format('Y-m-d H:i:s');
            $stale->write();
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = new \DateTime();

        $entry = static::create();
        $entry->Email = $email;
        $entry->Purpose = $purpose;
        $entry->MemberID = $memberID ?: 0;
        $entry->CodeHash = password_hash($code, PASSWORD_DEFAULT);
        $entry->Attempts = 0;
        $entry->ExpiresAt = (clone $now)->modify('+' . self::CODE_TTL_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        $entry->write();

        return [$entry, $code];
    }

    /**
     * Verifies $code for $email/$purpose. Returns the matching LoginCode on
     * success, or null (invalid/expired/too many attempts) — every attempt
     * (even a failed one) counts against the code's attempt limit.
     *
     * Does NOT mark the code as consumed — a login/signup may need a second
     * round trip (e.g. to collect firstName/surname for a brand new account)
     * where the same code must still verify. Call consume() once the flow
     * this code was for has actually completed.
     */
    public static function verify(string $email, string $purpose, string $code): ?self
    {
        $entry = static::get()->filter([
            'Email'      => $email,
            'Purpose'    => $purpose,
            'ConsumedAt' => null,
        ])->sort('Created DESC')->first();

        if (!$entry) {
            return null;
        }

        if (!$entry->ExpiresAt || strtotime($entry->ExpiresAt) < time()) {
            return null;
        }

        if ($entry->Attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        $entry->Attempts++;
        $entry->write();

        if (!password_verify($code, $entry->CodeHash)) {
            return null;
        }

        return $entry;
    }

    public function consume(): void
    {
        $this->ConsumedAt = (new \DateTime())->format('Y-m-d H:i:s');
        $this->write();
    }
}
