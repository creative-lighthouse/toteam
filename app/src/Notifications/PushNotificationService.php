<?php

namespace App\Notifications;

use App\Maps\Map;
use App\Food\Food;
use App\Food\Meal;
use App\Announcements\Announcement;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use App\Events\EventDay;
use App\Calendar\Appointment;
use App\Calendar\SchedulingPoll;
use SilverStripe\Security\Member;
use SilverStripe\Core\Environment;

/**
 * Service to send push notifications via Firebase Cloud Messaging
 */
class PushNotificationService
{
    private static ?string $cachedAccessToken = null;
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
    }

    /**
     * Send notification to a specific, pre-scoped list of member IDs (e.g. invited members
     * of an appointment), still respecting each member's Notify{Type} preference.
     */
    private static function sendToMemberList(array $memberIDs, $type, $title, $body, $url = null)
    {
        $field = 'Notify' . ucfirst($type);

        foreach ($memberIDs as $memberID) {
            self::saveNotification($memberID, $type, $title, $body, $url);

            $member = Member::get()->byID($memberID);
            if ($member && $member->$field) {
                self::sendToMember($member, $title, $body, $url);
            }
        }
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
     * Send notification for suggested appointment
     */
    public static function notifyAppointmentSuggested(Appointment $appointment)
    {
        $title = '💡 Terminvorschlag';
        $body = $appointment->Title . ' am ' . $appointment->RenderDate();
        $url = $appointment->getLink();

        self::sendToMemberList($appointment->InvitedMembers()->column('ID'), 'events', $title, $body, $url);
    }

    /**
     * Send notification for scheduled appointment
     */
    public static function notifyAppointmentScheduled(Appointment $appointment)
    {
        $title = '📅 Neuer Termin festgelegt';
        $body = $appointment->Title . ' am ' . $appointment->RenderDate();
        $url = $appointment->getLink();

        self::sendToMemberList($appointment->InvitedMembers()->column('ID'), 'events', $title, $body, $url);
    }

    /**
     * Send notification for cancelled appointment
     */
    public static function notifyAppointmentCancelled(Appointment $appointment)
    {
        $title = '❌ Termin abgesagt';
        $body = $appointment->Title . ' am ' . $appointment->RenderDate() . ' wurde abgesagt.';
        $url = $appointment->getLink();

        self::sendToMemberList($appointment->InvitedMembers()->column('ID'), 'events', $title, $body, $url);
    }

    /**
     * Send notification for a new scheduling poll (Terminfindung)
     */
    public static function notifyPollCreated(SchedulingPoll $poll)
    {
        $title = '🗳️ Neue Terminfindung';
        $body = $poll->Title;
        $url = $poll->getLink();

        self::sendToMemberList($poll->InvitedMembers()->column('ID'), 'events', $title, $body, $url);
    }

    /**
     * Send notification for new notice – only to members of linked organisations
     */
    public static function notifyNewAnnouncement(Announcement $announcement)
    {
        $organisations = $announcement->Organisations();

        if (!$organisations->exists()) {
            return;
        }

        $title = '📢 Neue Ankündigung';
        $body = $announcement->Title;
        $url = $announcement->getLink();

        $memberIDs = [];
        foreach ($organisations as $organisation) {
            $activeMembers = OrganizationMembership::get()->filter([
                'OrganizationID' => $organisation->ID,
                'Role'           => 'member',
            ]);
            foreach ($activeMembers as $membership) {
                $memberIDs[$membership->MemberID] = $membership->MemberID;
            }
        }

        foreach ($memberIDs as $memberID) {
            self::saveNotification($memberID, 'announcements', $title, $body, $url);

            $member = Member::get()->byID($memberID);
            if ($member && $member->NotifyAnnouncements) {
                self::sendToMember($member, $title, $body, $url);
            }
        }
    }

    public static function notifyNewApplication(OrganizationMembership $membership): void
    {
        $org      = $membership->Organization();
        $applicant = $membership->Member();

        if (!$org || !$applicant) {
            return;
        }

        $title = '📋 Neue Bewerbung';
        $body  = $applicant->FirstName . ' ' . $applicant->Surname . ' möchte "' . $org->Title . '" beitreten.';
        $url   = '/app/organizations?applicants=' . $org->ID;

        $candidateMemberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'Role'           => 'member',
        ]);

        foreach ($candidateMemberships as $candidateMembership) {
            $admin = $candidateMembership->Member();
            if (!$admin || !$admin->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS)) {
                continue;
            }

            self::saveNotification($admin->ID, 'applications', $title, $body, $url);

            if ($admin->NotifyApplications) {
                self::sendToMember($admin, $title, $body, $url);
            }
        }
    }

    public static function notifyNewMap(Map $map)
    {
        $title = 'Neuer Lageplan verfügbar';
        $body = $map->Title;
        $url = '/app/map';

        self::sendToUsers('maps', $title, $body, $url);
    }

    /**
     * Send notification for new meal
     */
    public static function notifyNewMeal(Meal $meal)
    {
        $title = 'Neuer Essensvorschlag';
        $body = $meal->Title;
        $url = '/app/food';

        self::sendToUsers('meals', $title, $body, $url);
    }

    /**
     * Benachrichtigt alle Mitglieder mit FOOD_APPROVE_SUGGESTIONS in der Organisation
     * über einen neuen, noch offenen Essens-Vorschlag.
     */
    public static function notifyFoodSuggestionPending(Food $food, Meal $meal): void
    {
        $appointment = $meal->Parent();
        $org = ($appointment && $appointment->exists()) ? $appointment->Organisations()->first() : null;
        if (!$org || !$org->exists()) {
            return;
        }

        $supplier = $food->Supplier();
        $title    = '🍽️ Neuer Essens-Vorschlag';
        $body     = ($supplier && $supplier->exists() ? trim($supplier->FirstName . ' ' . $supplier->Surname) . ' schlägt "' : 'Vorschlag: "')
            . $food->Title . '" für "' . $meal->Title . '" vor.';
        $url      = '/app/food/meal/' . $meal->ID;

        $candidateMemberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'Role'           => 'member',
        ]);

        foreach ($candidateMemberships as $candidateMembership) {
            $approver = $candidateMembership->Member();
            if (!$approver || !$approver->hasOrgPermission($org, OrgPermissions::FOOD_APPROVE_SUGGESTIONS)) {
                continue;
            }

            self::saveNotification($approver->ID, 'meals', $title, $body, $url);

            if ($approver->NotifyMeals) {
                self::sendToMember($approver, $title, $body, $url);
            }
        }
    }

    /**
     * Benachrichtigt den Vorschlagenden über die Entscheidung des Essensorganisators.
     */
    public static function notifyFoodSuggestionDecision(Food $food): void
    {
        $supplier = $food->Supplier();
        if (!$supplier || !$supplier->exists()) {
            return;
        }

        $accepted = $food->Status === 'Accepted';
        $title    = $accepted ? '✅ Vorschlag bestätigt' : '❌ Vorschlag abgelehnt';
        $body     = 'Dein Vorschlag "' . $food->Title . '" wurde ' . ($accepted ? 'bestätigt' : 'abgelehnt') . '.';
        $url      = '/app/food';

        self::saveNotification($supplier->ID, 'meals', $title, $body, $url);

        if ($supplier->NotifyMeals) {
            self::sendToMember($supplier, $title, $body, $url);
        }
    }

    /**
     * Get members who want to receive notification of specific type
     */
    private static function getMembersForNotification($type, $excludeMembers = [])
    {
        $field = 'Notify' . ucfirst($type);

        $membersWithTokens = NotificationToken::get()->column('MemberID');

        if (empty($membersWithTokens)) {
            return [];
        }

        $memberIDs = Member::get()
            ->filter(['ID' => $membersWithTokens, $field => true])
            ->column('ID');

        if (!empty($excludeMembers)) {
            $excludeIDs = array_map(fn($m) => is_object($m) ? $m->ID : $m, $excludeMembers);
            $memberIDs = array_diff($memberIDs, $excludeIDs);
        }

        return array_values($memberIDs);
    }

    /**
     * Send actual FCM notification
     */
    private static function sendNotification($token, $title, $body, $url = null)
    {
        if (!self::$cachedAccessToken) {
            self::$cachedAccessToken = self::getAccessToken();
        }

        $accessToken = self::$cachedAccessToken;

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
