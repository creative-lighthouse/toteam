<?php

namespace App\Notifications;

use App\Events\EventDay;
use App\Notices\Notice;
use App\Food\Meal;
use SilverStripe\Security\Member;
use SilverStripe\Core\Environment;

/**
 * Service to send push notifications via Firebase Cloud Messaging
 */
class PushNotificationService
{
    /**
     * Send notification to all users with specific preference enabled
     */
    public static function sendToUsers($type, $title, $body, $url = null, array $excludeMembers = [])
    {
        // Get all member IDs who want this type of notification
        $memberIDs = self::getMembersForNotification($type, $excludeMembers);

        foreach ($memberIDs as $memberID) {
            // Save notification to inbox
            self::saveNotification($memberID, $type, $title, $body, $url);

            // Send push notification
            $member = Member::get()->byID($memberID);
            if ($member) {
                self::sendToMember($member, $title, $body, $url);
            }
        }
    }    /**
     * Send notification to specific member
     */
    public static function sendToMember(Member $member, $title, $body, $url = null)
    {
        $tokens = NotificationToken::get()->filter('MemberID', $member->ID);

        foreach ($tokens as $tokenObj) {
            self::sendNotification($tokenObj->Token, $title, $body, $url);
        }
    }

    /**
     * Save notification to inbox for member
     */
    private static function saveNotification($memberID, $type, $title, $body, $url = null)
    {
        SavedNotification::createNotification($memberID, $type, $title, $body, $url);
    }    /**
     * Send notification for suggested event
     */
    public static function notifyEventSuggested(EventDay $event)
    {
        $title = '💡 Terminvorschlag';
        $body = $event->Title . ' am ' . $event->RenderDate();
        $url = $event->getLink();

        self::sendToUsers('events', $title, $body, $url);
    }

    /**
     * Send notification for scheduled event
     */
    public static function notifyEventScheduled(EventDay $event)
    {
        $title = '📅 Neuer Termin festgelegt';
        $body = $event->Title . ' am ' . $event->RenderDate();
        $url = $event->getLink();

        self::sendToUsers('events', $title, $body, $url);
    }

    /**
     * Send notification for cancelled event
     */
    public static function notifyEventCancelled(EventDay $event)
    {
        $title = '❌ Termin abgesagt';
        $body = $event->Title . ' am ' . $event->RenderDate() . ' wurde abgesagt.';
        $url = $event->getLink();

        self::sendToUsers('events', $title, $body, $url);
    }

    /**
     * Send notification for new notice
     */
    public static function notifyNewNotice(Notice $notice)
    {
        $title = 'Neue Nachricht';
        $body = $notice->Title;
        $url = '/notices';

        self::sendToUsers('notices', $title, $body, $url);
    }

    /**
     * Send notification for new meal
     */
    public static function notifyNewMeal(Meal $meal)
    {
        $title = 'Neuer Essensvorschlag';
        $body = $meal->Title;
        $url = '/food/meal/' . $meal->ID;

        self::sendToUsers('meals', $title, $body, $url);
    }

    /**
     * Get members who want to receive notification of specific type
     */
    private static function getMembersForNotification($type, $excludeMembers = [])
    {
        $field = 'Notify' . ucfirst($type);

        // Get all members with tokens
        $membersWithTokens = NotificationToken::get()->column('MemberID');

        if (empty($membersWithTokens)) {
            return [];
        }

        // Get preferences that have this notification type DISABLED
        $disabledPrefs = NotificationPreference::get()->filter($field, false);
        $disabledMemberIDs = $disabledPrefs->column('MemberID');

        // Filter out disabled members
        $memberIDs = array_diff($membersWithTokens, $disabledMemberIDs);

        if (!empty($excludeMembers)) {
            $excludeIDs = array_map(function ($m) {
                return is_object($m) ? $m->ID : $m;
            }, $excludeMembers);
            $memberIDs = array_diff($memberIDs, $excludeIDs);
        }

        return array_values($memberIDs);
    }

    /**
     * Send actual FCM notification
     */
    private static function sendNotification($token, $title, $body, $url = null)
    {
        $accessToken = self::getAccessToken();

        if (!$accessToken) {
            error_log('Failed to get FCM access token');
            return false;
        }

        $projectId = Environment::getEnv('VITE_FIREBASE_PROJECT_ID');        // Use data-only messages to avoid duplicate notifications
        // Service Worker will handle creating the notification
        $data = [
            'message' => [
                'token' => $token,
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    'url' => $url ?? '/'
                ]
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('FCM Error (HTTP ' . $httpCode . '): ' . $result);
            return false;
        }

        return true;
    }

    /**
     * Get OAuth2 access token for FCM using service account
     */
    private static function getAccessToken()
    {
        $serviceAccountPath = BASE_PATH . '/firebase-service-account.json';

        if (!file_exists($serviceAccountPath)) {
            error_log('Firebase service account file not found at: ' . $serviceAccountPath);
            return null;
        }

        $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

        if (!$serviceAccount) {
            error_log('Failed to parse service account JSON');
            return null;
        }

        // Create JWT
        $now = time();
        $payload = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        ];

        // Use URL-safe base64 encoding for JWT
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        $signature = '';
        openssl_sign(
            $header . '.' . $payload,
            $signature,
            $serviceAccount['private_key'],
            OPENSSL_ALGO_SHA256
        );

        $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $jwt = $header . '.' . $payload . '.' . $signature;

        // Exchange JWT for access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            error_log('Failed to get OAuth2 access token. Response: ' . $response);
            return null;
        }

        return $data['access_token'];
    }
}
