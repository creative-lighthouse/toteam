<?php

namespace App\Controllers;

use App\Notifications\NotificationToken;
use App\Notifications\SavedNotification;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;

/**
 * Class \App\Controllers\NotificationApiController
 *
 */
class NotificationApiController extends Controller
{
    private static $url_handlers = [
        'POST save-token' => 'saveToken',
        'POST update-preferences' => 'updatePreferences',
        'GET preferences' => 'getPreferences',
        'GET test-notification' => 'testNotification',
        'GET inbox' => 'getInbox',
        'GET unread-count' => 'getUnreadCount',
        'POST $ID/mark-read' => 'markAsRead',
        'POST mark-all-read' => 'markAllAsRead'
    ];

    private static $allowed_actions = [
        'saveToken',
        'updatePreferences',
        'getPreferences',
        'testNotification',
        'getInbox',
        'getUnreadCount',
        'markAsRead',
        'markAllAsRead'
    ];

    /**
     * Save FCM token for current user
     */
    public function saveToken(HTTPRequest $request)
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getBody(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            return $this->jsonResponse(['error' => 'Token required'], 400);
        }

        try {
            NotificationToken::updateToken($token, $member);
            return $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(HTTPRequest $request)
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getBody(), true);

        if (isset($data['events']))  $member->NotifyEvents  = (bool)$data['events'];
        if (isset($data['announcements'])) $member->NotifyAnnouncements = (bool)$data['announcements'];
        if (isset($data['meals']))   $member->NotifyMeals   = (bool)$data['meals'];
        if (isset($data['maps']))    $member->NotifyMaps    = (bool)$data['maps'];

        $member->write();
        return $this->jsonResponse(['success' => true]);
    }

    /**
     * Get current user's notification preferences
     */
    public function getPreferences(HTTPRequest $request)
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        return $this->jsonResponse([
            'events'  => (bool)$member->NotifyEvents,
            'announcements' => (bool)$member->NotifyAnnouncements,
            'meals'   => (bool)$member->NotifyMeals,
            'maps'    => (bool)$member->NotifyMaps,
        ]);
    }

    /**
     * Test notification endpoint
     */
    public function testNotification(HTTPRequest $request)
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        try {
            \App\Notifications\PushNotificationService::sendToMember(
                $member,
                'Test Benachrichtigung',
                'Dies ist eine Test-Nachricht von ToTeam!',
                '/dashboard'
            );

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Test notification sent to ' . $member->getName()
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification inbox for current user
     */
    public function getInbox(HTTPRequest $request)
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $limit = max(1, min(50, (int)($request->getVar('limit') ?? 20)));
        $offset = max(0, (int)($request->getVar('offset') ?? 0));

        $base = SavedNotification::get()->filter('MemberID', $member->ID);
        $total = $base->count();

        // Unread first, then by Created DESC
        $notifications = $base
            ->sort('IsRead ASC, Created DESC')
            ->limit($limit, $offset);

        $data = [];
        foreach ($notifications as $notification) {
            $data[] = [
                'id' => $notification->ID,
                'title' => $notification->Title,
                'body' => $notification->Body,
                'type' => $notification->Type,
                'url' => $notification->URL,
                'icon' => $notification->getIcon(),
                'isRead' => (bool)$notification->IsRead,
                'created' => $notification->Created
            ];
        }

        return $this->jsonResponse([
            'notifications' => $data,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'hasMore' => ($offset + $limit) < $total
        ]);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(HTTPRequest $request)
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $count = SavedNotification::getUnreadCount($member->ID);

        return $this->jsonResponse(['count' => $count]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(HTTPRequest $request)
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $notificationID = $request->param('ID');
        $notification = SavedNotification::get()->byID($notificationID);

        if (!$notification || $notification->MemberID != $member->ID) {
            return $this->jsonResponse(['error' => 'Notification not found'], 404);
        }

        $notification->markAsRead();

        return $this->jsonResponse(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(HTTPRequest $request)
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        SavedNotification::markAllAsRead($member->ID);

        return $this->jsonResponse(['success' => true]);
    }

    /**
     * Return JSON response
     */
    private function jsonResponse($data, $status = 200)
    {
        $response = new HTTPResponse(json_encode($data), $status);
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }
}
