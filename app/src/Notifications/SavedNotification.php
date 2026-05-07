<?php

namespace App\Notifications;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Saved notification for the notification inbox
 *
 * @property ?string $Title
 * @property ?string $Body
 * @property ?string $Type
 * @property ?string $URL
 * @property bool $IsRead
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class SavedNotification extends DataObject
{
    private static $table_name = 'SavedNotification';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Body' => 'Text',
        'Type' => 'Enum("events,notices,announcements,meals","events")',
        'URL' => 'Varchar(255)',
        'IsRead' => 'Boolean'
    ];

    private static $has_one = [
        'Member' => Member::class
    ];

    private static $default_sort = 'Created DESC';

    private static $defaults = [
        'IsRead' => false
    ];

    private static $summary_fields = [
        'Created.Nice' => 'Datum',
        'Title' => 'Titel',
        'Type' => 'Typ',
        'IsRead.Nice' => 'Gelesen'
    ];

    private static $searchable_fields = [
        'Title',
        'Type',
        'IsRead'
    ];

    /**
     * Create a new saved notification
     */
    public static function createNotification($memberID, $type, $title, $body, $url = null)
    {
        $notification = self::create();
        $notification->MemberID = $memberID;
        $notification->Type = $type;
        $notification->Title = $title;
        $notification->Body = $body;
        $notification->URL = $url ?? '/';
        $notification->write();

        return $notification;
    }

    /**
     * Get unread count for member
     */
    public static function getUnreadCount($memberID)
    {
        return self::get()->filter([
            'MemberID' => $memberID,
            'IsRead' => false
        ])->count();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->IsRead = true;
        $this->write();
    }

    /**
     * Mark all notifications as read for a member
     */
    public static function markAllAsRead($memberID)
    {
        $notifications = self::get()->filter([
            'MemberID' => $memberID,
            'IsRead' => false
        ]);

        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }
    }

    /**
     * Get icon for notification type
     */
    public function getIcon()
    {
        switch ($this->Type) {
            case 'events':
                return '📅';
            case 'notices':
            case 'announcements':
                return '📢';
            case 'meals':
                return '🍽️';
            default:
                return '🔔';
        }
    }
}
