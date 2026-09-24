<?php

namespace App\Auth;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * One row per device/browser a Member is currently (or was) logged into.
 * Holds a hashed refresh token so an access token can be silently renewed,
 * and can be individually revoked ("log out this device") without affecting
 * the member's other sessions.
 *
 * @property string $RefreshTokenHash
 * @property ?string $DeviceLabel
 * @property ?string $UserAgent
 * @property ?string $IPAddress
 * @property ?string $LastUsedAt
 * @property ?string $ExpiresAt
 * @property ?string $RevokedAt
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 */
class LoginSession extends DataObject
{
    private const REFRESH_TOKEN_TTL_DAYS = 60;

    /**
     * So lange nach einer Rotation wird das vorherige Refresh-Token noch akzeptiert.
     * Alle Tabs teilen sich denselben Cookie: refreshen zwei Tabs (fast) gleichzeitig,
     * schickt der zweite noch das alte Token mit — ohne Karenzzeit würde er abgewiesen
     * und die Sitzung wirkte in allen Tabs ausgeloggt.
     */
    public const ROTATION_GRACE_SECONDS = 120;

    private static $table_name = 'LoginSession';
    private static $singular_name = 'Login-Sitzung';
    private static $plural_name = 'Login-Sitzungen';

    private static $db = [
        'RefreshTokenHash' => 'Varchar(255)',
        // Vorheriges Token nach einer Rotation — bleibt für ROTATION_GRACE_SECONDS gültig
        'PreviousTokenHash' => 'Varchar(255)',
        'RotatedAt'        => 'Datetime',
        'DeviceLabel'      => 'Varchar(255)',
        'UserAgent'        => 'Varchar(255)',
        'IPAddress'        => 'Varchar(64)',
        'LastUsedAt'       => 'Datetime',
        'ExpiresAt'        => 'Datetime',
        'RevokedAt'        => 'Datetime',
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $indexes = [
        'RefreshTokenHash' => true,
        'PreviousTokenHash' => true,
    ];

    private static $default_sort = 'LastUsedAt DESC';

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Creates a new session row for $member and returns the plaintext refresh
     * token (only ever available at creation time — only the hash is stored).
     */
    public static function start(Member $member, string $userAgent, string $ip): array
    {
        $token = bin2hex(random_bytes(32));
        $now = new \DateTime();

        $session = static::create();
        $session->MemberID = $member->ID;
        $session->RefreshTokenHash = static::hashToken($token);
        $session->DeviceLabel = static::labelFromUserAgent($userAgent);
        $session->UserAgent = $userAgent;
        $session->IPAddress = $ip;
        $session->LastUsedAt = $now->format('Y-m-d H:i:s');
        $session->ExpiresAt = (clone $now)->modify('+' . self::REFRESH_TOKEN_TTL_DAYS . ' days')->format('Y-m-d H:i:s');
        $session->write();

        return [$session, $token];
    }

    /**
     * Rotates this session's refresh token (called on every successful
     * refresh) and returns the new plaintext token.
     */
    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $now = new \DateTime();

        $this->PreviousTokenHash = $this->RefreshTokenHash;
        $this->RotatedAt = $now->format('Y-m-d H:i:s');
        $this->RefreshTokenHash = static::hashToken($token);
        $this->LastUsedAt = $now->format('Y-m-d H:i:s');
        $this->ExpiresAt = (clone $now)->modify('+' . self::REFRESH_TOKEN_TTL_DAYS . ' days')->format('Y-m-d H:i:s');
        $this->write();

        return $token;
    }

    /**
     * Sucht die Sitzung zu einem Refresh-Token — das aktuelle oder, innerhalb der
     * Karenzzeit nach einer Rotation, das vorherige.
     *
     * @return array{0: ?LoginSession, 1: bool} [Sitzung, ob es das vorherige Token war]
     */
    public static function findByToken(?string $token): array
    {
        if (!$token) {
            return [null, false];
        }

        $hash = static::hashToken($token);
        $session = static::get()->filter('RefreshTokenHash', $hash)->first();
        if ($session) {
            return [$session, false];
        }

        $session = static::get()->filter('PreviousTokenHash', $hash)->first();
        if ($session && $session->RotatedAt
            && time() - strtotime((string) $session->RotatedAt) <= self::ROTATION_GRACE_SECONDS) {
            return [$session, true];
        }

        return [null, false];
    }

    /**
     * Das Mitglied zu einem Refresh-Token, sofern die Sitzung noch gültig ist.
     * Rotiert das Token nicht — nur zum Prüfen, ob jemand eingeloggt ist.
     */
    public static function findMemberByToken(?string $token): ?Member
    {
        [$session] = static::findByToken($token);
        if (!$session || !$session->isValid()) {
            return null;
        }

        $member = $session->Member();
        return $member && $member->exists() ? $member : null;
    }

    public function isValid(): bool
    {
        if ($this->RevokedAt) {
            return false;
        }
        if (!$this->ExpiresAt || strtotime($this->ExpiresAt) < time()) {
            return false;
        }
        return true;
    }

    public function revoke(): void
    {
        $this->RevokedAt = (new \DateTime())->format('Y-m-d H:i:s');
        $this->write();
    }

    private static function labelFromUserAgent(string $userAgent): string
    {
        $browser = 'Unbekannter Browser';
        if (str_contains($userAgent, 'Edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($userAgent, 'Chrome/') && !str_contains($userAgent, 'Chromium')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari/') && !str_contains($userAgent, 'Chrome')) {
            $browser = 'Safari';
        }

        $os = '';
        if (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'Mac OS X')) {
            $os = 'macOS';
        } elseif (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        }

        return $os ? "{$browser} auf {$os}" : $browser;
    }

    public function canView($member = null, $context = [])
    {
        return $member && (int) $member->ID === (int) $this->MemberID;
    }

    public function canEdit($member = null, $context = [])
    {
        return $this->canView($member, $context);
    }

    public function canDelete($member = null, $context = [])
    {
        return $this->canView($member, $context);
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }
}
