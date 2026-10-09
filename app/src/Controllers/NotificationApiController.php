<?php

namespace App\Controllers;

use App\Notifications\NotificationToken;
use App\Notifications\PushNotificationService;
use App\Notifications\SavedNotification;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Benachrichtigungs-Inbox und Push-Tokens. Erreichbar unter /api/v1/notifications (Vue-App,
 * Anmeldung per Bearer-Token) und /api/notifications (alte Seiten, Anmeldung per Session).
 */
class NotificationApiController extends ApiController
{
    private static $url_handlers = [
        'POST save-token' => 'saveToken',
        'POST remove-token' => 'removeToken',
        'POST update-preferences' => 'updatePreferences',
        'GET preferences' => 'getPreferences',
        'POST test-notification' => 'testNotification',
        'GET inbox' => 'getInbox',
        'GET unread-count' => 'getUnreadCount',
        'POST $ID/mark-read' => 'markAsRead',
        'POST mark-all-read' => 'markAllAsRead'
    ];

    private static $allowed_actions = [
        'saveToken',
        'removeToken',
        'updatePreferences',
        'getPreferences',
        'testNotification',
        'getInbox',
        'getUnreadCount',
        'markAsRead',
        'markAllAsRead'
    ];

    /**
     * Bearer-Token der Vue-App, sonst die PHP-Session der alten Seiten
     */
    private function currentMember(): ?Member
    {
        return $this->requireAuth() ?? Security::getCurrentUser();
    }

    /**
     * Save FCM token for current user
     */
    public function saveToken(HTTPRequest $request)
    {
        $member = $this->currentMember();

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
     * Token dieses Geräts entfernen (beim Abmelden), damit es keine Pushes mehr bekommt
     */
    public function removeToken(HTTPRequest $request)
    {
        $member = $this->currentMember();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getBody(), true);
        $token = $data['token'] ?? null;

        if ($token) {
            NotificationToken::get()->filter(['Token' => $token, 'MemberID' => $member->ID])->removeAll();
        }

        return $this->jsonResponse(['success' => true]);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(HTTPRequest $request)
    {
        $member = $this->currentMember();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getBody(), true);

        if (isset($data['events'])) {
            $member->NotifyEvents  = (bool)$data['events'];
        }
        if (isset($data['announcements'])) {
            $member->NotifyAnnouncements = (bool)$data['announcements'];
        }
        if (isset($data['meals'])) {
            $member->NotifyMeals   = (bool)$data['meals'];
        }
        if (isset($data['maps'])) {
            $member->NotifyMaps    = (bool)$data['maps'];
        }

        $member->write();
        return $this->jsonResponse(['success' => true]);
    }

    /**
     * Get current user's notification preferences
     */
    public function getPreferences(HTTPRequest $request)
    {
        $member = $this->currentMember();

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
     * Test-Push an alle Geräte des aktuellen Nutzers (ohne Inbox-Eintrag)
     */
    public function testNotification(HTTPRequest $request)
    {
        $member = $this->currentMember();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $devices = NotificationToken::get()->filter('MemberID', $member->ID)->count();
        if (!$devices) {
            return $this->jsonResponse(['success' => false, 'error' => 'Für dein Konto ist kein Gerät registriert.'], 400);
        }

        if (!PushNotificationService::isConfigured()) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Push ist auf dem Server nicht eingerichtet (firebase-service-account.json fehlt).'
            ], 500);
        }

        $sent = PushNotificationService::sendToMember(
            $member,
            '🔔 Test-Benachrichtigung',
            'Push-Benachrichtigungen funktionieren auf diesem Gerät.',
            '/app/dashboard'
        );

        if (!$sent) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Die Benachrichtigung konnte nicht verschickt werden (Server-Log prüfen).'
            ], 500);
        }

        return $this->jsonResponse(['success' => true, 'devices' => $sent]);
    }

    /**
     * Ungelesene Benachrichtigungen des aktuellen Nutzers, neueste zuerst.
     * Gelesene bleiben in der DB (CMS), werden aber nicht mehr ausgeliefert.
     */
    public function getInbox(HTTPRequest $request)
    {
        $member = $this->currentMember();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        $limit = max(1, min(50, (int)($request->getVar('limit') ?? 20)));
        $offset = max(0, (int)($request->getVar('offset') ?? 0));

        $base = SavedNotification::get()->filter([
            'MemberID' => $member->ID,
            'IsRead' => false
        ]);
        $total = $base->count();

        $notifications = $base
            ->sort('Created DESC')
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
                'isRead' => false,
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
        $member = $this->currentMember();

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
        $member = $this->currentMember();

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
        $member = $this->currentMember();

        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        SavedNotification::markAllAsRead($member->ID);

        return $this->jsonResponse(['success' => true]);
    }
}
