<?php

namespace App\Notifications;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Notifications\NotificationToken
 *
 * @property ?string $Token
 * @property ?string $DeviceInfo
 * @property ?string $LastUsed
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class NotificationToken extends DataObject
{
    private static $db = [
        'Token' => 'Varchar(255)',
        'DeviceInfo' => 'Varchar(255)',
        'LastUsed' => 'Datetime'
    ];

    private static $has_one = [
        'Member' => Member::class
    ];

    private static $summary_fields = [
        'Member.Name' => 'Member',
        'DeviceInfo' => 'Device',
        'LastUsed' => 'Last Used'
    ];

    private static $table_name = 'NotificationToken';
    private static $singular_name = 'Notification Token';
    private static $plural_name = 'Notification Tokens';

    /**
     * Update or create token for current user
     */
    public static function updateToken($token, $member)
    {
        $existing = self::get()->filter([
            'Token' => $token,
            'MemberID' => $member->ID
        ])->first();

        if ($existing) {
            $existing->LastUsed = date('Y-m-d H:i:s');
            $existing->write();
            return $existing;
        }

        $newToken = self::create();
        $newToken->Token = $token;
        $newToken->MemberID = $member->ID;
        $newToken->DeviceInfo = self::getDeviceInfo();
        $newToken->LastUsed = date('Y-m-d H:i:s');
        $newToken->write();

        return $newToken;
    }

    /**
     * Get device info from user agent
     */
    private static function getDeviceInfo()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
            return 'Mobile';
        }

        return 'Desktop';
    }
}
