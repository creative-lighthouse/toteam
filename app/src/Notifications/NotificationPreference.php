<?php

namespace App\Notifications;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Notifications\NotificationPreference
 *
 * @property bool $NotifyEvents
 * @property bool $NotifyNotices
 * @property bool $NotifyMeals
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class NotificationPreference extends DataObject
{
    private static $db = [
        'NotifyEvents' => 'Boolean(1)',
        'NotifyNotices' => 'Boolean(1)',
        'NotifyMeals' => 'Boolean(1)'
    ];

    private static $has_one = [
        'Member' => Member::class
    ];

    private static $defaults = [
        'NotifyEvents' => true,
        'NotifyNotices' => true,
        'NotifyMeals' => true
    ];

    private static $table_name = 'NotificationPreference';
    private static $singular_name = 'Notification Preference';
    private static $plural_name = 'Notification Preferences';

    /**
     * Get or create preferences for member
     */
    public static function getForMember(Member $member)
    {
        $prefs = self::get()->filter('MemberID', $member->ID)->first();

        if (!$prefs) {
            $prefs = self::create();
            $prefs->MemberID = $member->ID;
            $prefs->write();
        }

        return $prefs;
    }

    /**
     * Update preferences for member
     */
    public static function updatePreferences(Member $member, array $data)
    {
        $prefs = self::getForMember($member);

        if (isset($data['events'])) {
            $prefs->NotifyEvents = (bool)$data['events'];
        }

        if (isset($data['notices'])) {
            $prefs->NotifyNotices = (bool)$data['notices'];
        }

        if (isset($data['meals'])) {
            $prefs->NotifyMeals = (bool)$data['meals'];
        }

        $prefs->write();

        return $prefs;
    }
}
