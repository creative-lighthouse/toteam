<?php

namespace App\Notifications;

use App\Maps\Map;
use App\Food\Food;
use App\Food\Meal;
use App\Announcements\FeedPost;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgPermissions;
use App\Teams\OrgEvent;
use App\Events\EventDay;
use App\Calendar\Appointment;
use App\Calendar\SchedulingPoll;
use App\Inventory\InventoryDamageReport;
use App\Inventory\InventoryRental;
use SilverStripe\Security\Member;
use SilverStripe\Core\Environment;

/**
 * Benachrichtigungen: Inbox-Eintrag (SavedNotification) + Push via Firebase Cloud Messaging.
 *
 * Jede notify*()-Methode bestimmt nur, WER betroffen ist und zu welcher Organisation die
 * Sache gehört, und übergibt an {@see deliver()}. Dort gilt für alle gleich:
 *  - nur Mitglieder (Role "member", keine Bewerber) dieser Organisation(en) kommen in Frage,
 *  - ob sie die Benachrichtigung bekommen, entscheidet allein ihre Einstellung
 *    (Member.Notify<Typ>): an → Inbox-Eintrag und Push auf alle registrierten Geräte,
 *    aus → gar nichts.
 */
class PushNotificationService
{
    /** Benachrichtigungs-Typ → Einstellung am Member (siehe SettingsModal) */
    public const TYPE_SETTINGS = [
        'events'        => 'NotifyEvents',
        'announcements' => 'NotifyAnnouncements',
        'meals'         => 'NotifyMeals',
        'maps'          => 'NotifyMaps',
        'applications'  => 'NotifyApplications',
        'inventory'     => 'NotifyInventory',
    ];

    private static ?string $cachedAccessToken = null;
    private static int $cachedAccessTokenExpires = 0;

    /**
     * Zentrale Zustellung: an alle $memberIDs (ohne $excludeIDs), die Mitglied in einer der
     * $orgIDs sind und den Typ in ihren Einstellungen aktiviert haben — Inbox-Eintrag und
     * Push auf alle ihre Geräte. $orgIDs = null nur für rein persönliche Dinge ohne
     * Organisation (privat verliehenes Equipment).
     */
    public static function deliver(iterable $memberIDs, ?array $orgIDs, string $type, string $title, string $body, ?string $url = null, array $excludeIDs = []): void
    {
        $setting = self::TYPE_SETTINGS[$type] ?? null;
        if (!$setting) {
            throw new \InvalidArgumentException('Unbekannter Benachrichtigungs-Typ: ' . $type);
        }

        $ids = array_diff(
            array_unique(array_map('intval', is_array($memberIDs) ? $memberIDs : iterator_to_array($memberIDs, false))),
            array_map('intval', $excludeIDs),
            [0]
        );

        if ($orgIDs !== null) {
            $orgIDs = array_filter(array_map('intval', $orgIDs));
            $orgMemberIDs = $orgIDs
                ? OrganizationMembership::get()->filter([
                    'OrganizationID' => $orgIDs,
                    'Role'           => 'member',
                ])->column('MemberID')
                : [];
            $ids = array_intersect($ids, array_map('intval', $orgMemberIDs));
        }

        if (empty($ids)) {
            return;
        }

        foreach (Member::get()->filter(['ID' => $ids, $setting => true]) as $member) {
            SavedNotification::createNotification($member->ID, $type, $title, $body, $url);
            self::sendToMember($member, $title, $body, $url);
        }
    }

    /**
     * Push an alle registrierten Geräte eines Members, ohne Inbox-Eintrag und ohne
     * Einstellungs-Prüfung (nur für den Test-Button) — gibt die Zahl erreichter Geräte zurück
     */
    public static function sendToMember(Member $member, $title, $body, $url = null): int
    {
        $tokens = NotificationToken::get()->filter('MemberID', $member->ID);
        $sent = 0;

        foreach ($tokens as $tokenObj) {
            if (self::sendNotification($tokenObj->Token, $title, $body, $url)) {
                $sent++;
            }
        }

        return $sent;
    }

    /** IDs der (existierenden) Organisationen */
    private static function orgIDs(?Organization ...$orgs): array
    {
        return array_values(array_unique(array_map(
            fn (Organization $org) => (int) $org->ID,
            array_filter($orgs, fn (?Organization $org) => $org && $org->exists())
        )));
    }

    /** Mitglieder (keine Bewerber) einer Organisation */
    private static function orgMemberIDs(?Organization $org): array
    {
        if (!$org || !$org->exists()) {
            return [];
        }

        return OrganizationMembership::get()->filter([
            'OrganizationID' => $org->ID,
            'Role'           => 'member',
        ])->column('MemberID');
    }

    /** Mitglieder einer Organisation mit einer bestimmten Org-Berechtigung */
    private static function orgMemberIDsWithPermission(?Organization $org, string $permission): array
    {
        if (!$org || !$org->exists()) {
            return [];
        }

        $ids = [];
        foreach (Member::get()->filter('ID', self::orgMemberIDs($org) ?: [0]) as $member) {
            if ($member->hasOrgPermission($org, $permission)) {
                $ids[] = $member->ID;
            }
        }
        return $ids;
    }

    // ── Termine (alt: EventDay) ─────────────────────────────────────────────

    public static function notifyEventSuggested(EventDay $event)
    {
        self::deliver(
            self::orgMemberIDs($event->Organisation()),
            self::orgIDs($event->Organisation()),
            'events',
            '💡 Terminvorschlag',
            $event->Title . ' am ' . $event->RenderDate(),
            $event->getLink()
        );
    }

    public static function notifyEventScheduled(EventDay $event)
    {
        self::deliver(
            self::orgMemberIDs($event->Organisation()),
            self::orgIDs($event->Organisation()),
            'events',
            '📅 Neuer Termin festgelegt',
            $event->Title . ' am ' . $event->RenderDate(),
            $event->getLink()
        );
    }

    public static function notifyEventCancelled(EventDay $event)
    {
        self::deliver(
            self::orgMemberIDs($event->Organisation()),
            self::orgIDs($event->Organisation()),
            'events',
            '❌ Termin abgesagt',
            $event->Title . ' am ' . $event->RenderDate() . ' wurde abgesagt.',
            $event->getLink()
        );
    }

    // ── Termine (Appointment) und Terminfindungen: an die Eingeladenen ──────

    public static function notifyAppointmentSuggested(Appointment $appointment)
    {
        self::deliver(
            $appointment->InvitedMembers()->column('ID'),
            $appointment->Organisations()->column('ID'),
            'events',
            '💡 Terminvorschlag',
            $appointment->Title . ' am ' . $appointment->RenderDate(),
            $appointment->getLink()
        );
    }

    public static function notifyAppointmentScheduled(Appointment $appointment)
    {
        self::deliver(
            $appointment->InvitedMembers()->column('ID'),
            $appointment->Organisations()->column('ID'),
            'events',
            '📅 Neuer Termin festgelegt',
            $appointment->Title . ' am ' . $appointment->RenderDate(),
            $appointment->getLink()
        );
    }

    public static function notifyAppointmentCancelled(Appointment $appointment)
    {
        self::deliver(
            $appointment->InvitedMembers()->column('ID'),
            $appointment->Organisations()->column('ID'),
            'events',
            '❌ Termin abgesagt',
            $appointment->Title . ' am ' . $appointment->RenderDate() . ' wurde abgesagt.',
            $appointment->getLink()
        );
    }

    public static function notifyPollCreated(SchedulingPoll $poll)
    {
        self::deliver(
            $poll->InvitedMembers()->column('ID'),
            $poll->Organisations()->column('ID'),
            'events',
            '🗳️ Neue Terminfindung',
            $poll->Title,
            $poll->getLink()
        );
    }

    // ── Feed ─────────────────────────────────────────────────────────────────

    /**
     * Neuer Feed-Beitrag im Namen einer Organisation — an deren Mitglieder (außer
     * dem Verfasser)
     */
    public static function notifyNewFeedPost(FeedPost $post): void
    {
        $org = $post->Organization();
        if (!$org || !$org->exists()) {
            return;
        }

        $event = $post->EventID ? $post->Event() : null;
        // Geteiltes Event ohne eigenen Text: das Event nennen
        $body = $post->getExcerpt() ?: ($event && $event->exists() ? '📅 ' . $event->Title : '');

        self::deliver(
            self::orgMemberIDs($org),
            self::orgIDs($org),
            'announcements',
            '📢 ' . $org->Title,
            $body,
            $post->getLink(),
            [$post->AuthorID]
        );
    }

    // ── Organisationen ───────────────────────────────────────────────────────

    /** Neue Bewerbung — an alle, die Mitglieder der Organisation verwalten dürfen */
    public static function notifyNewApplication(OrganizationMembership $membership): void
    {
        $org = $membership->Organization();
        $applicant = $membership->Member();

        if (!$org || !$org->exists() || !$applicant || !$applicant->exists()) {
            return;
        }

        self::deliver(
            self::orgMemberIDsWithPermission($org, OrgPermissions::ORG_MANAGE_MEMBERS),
            self::orgIDs($org),
            'applications',
            '📋 Neue Bewerbung',
            $applicant->FirstName . ' ' . $applicant->Surname . ' möchte "' . $org->Title . '" beitreten.',
            '/app/organizations?applicants=' . $org->ID
        );
    }

    // ── Lagepläne ────────────────────────────────────────────────────────────

    /** Neuer Lageplan — an die Mitglieder der Organisation, der er gehört (außer dem Autor) */
    public static function notifyNewMap(Map $map)
    {
        self::deliver(
            self::orgMemberIDs($map->Parent()),
            self::orgIDs($map->Parent()),
            'maps',
            '🗺️ Neuer Lageplan verfügbar',
            (string) $map->Title,
            '/app/map/' . $map->ID,
            [$map->AuthorID]
        );
    }

    // ── Essen ────────────────────────────────────────────────────────────────

    /** Neue Mahlzeit — an die zum Termin eingeladenen Personen */
    public static function notifyNewMeal(Meal $meal)
    {
        $appointment = $meal->Parent();
        if (!$appointment || !$appointment->exists()) {
            return;
        }

        self::deliver(
            $appointment->InvitedMembers()->column('ID'),
            $appointment->Organisations()->column('ID'),
            'meals',
            '🍽️ Neue Mahlzeit',
            $meal->Title . ' – ' . $appointment->Title . ' am ' . $appointment->RenderDate(),
            '/app/food/meal/' . $meal->ID
        );
    }

    /**
     * Neuer, noch offener Essens-Vorschlag für eine Mahlzeit — an alle mit
     * FOOD_APPROVE_SUGGESTIONS in der Organisation des Termins
     */
    public static function notifyFoodSuggestionPending(Food $food, Meal $meal): void
    {
        $appointment = $meal->Parent();
        $org = ($appointment && $appointment->exists()) ? $appointment->Organisations()->first() : null;

        self::deliver(
            self::orgMemberIDsWithPermission($org, OrgPermissions::FOOD_APPROVE_SUGGESTIONS),
            self::orgIDs($org),
            'meals',
            '🍽️ Neuer Essens-Vorschlag',
            self::suggestionText($food, $meal->Title),
            '/app/food/meal/' . $meal->ID,
            [$food->SupplierID]
        );
    }

    /**
     * Neuer Vorschlag für ein Event (noch ohne Mahlzeit): an alle Essensplaner der
     * Organisation, mit Link direkt in den Planer dieses Events.
     */
    public static function notifyFoodSuggestionForEvent(Food $food, OrgEvent $event): void
    {
        self::deliver(
            self::orgMemberIDsWithPermission($event->Organization(), OrgPermissions::FOOD_APPROVE_SUGGESTIONS),
            self::orgIDs($event->Organization()),
            'meals',
            '🍽️ Neuer Essens-Vorschlag',
            self::suggestionText($food, $event->Title),
            '/app/food?tab=plan&event=' . $event->ID,
            [$food->SupplierID]
        );
    }

    private static function suggestionText(Food $food, ?string $target): string
    {
        $supplier = $food->Supplier();
        return ($supplier && $supplier->exists() ? trim($supplier->FirstName . ' ' . $supplier->Surname) . ' schlägt "' : 'Vorschlag: "')
            . $food->Title . '" für "' . $target . '" vor.';
    }

    /** Entscheidung des Essensplaners — an die vorschlagende Person */
    public static function notifyFoodSuggestionDecision(Food $food): void
    {
        $accepted = $food->Status === 'Accepted';
        // Organisation des Vorschlags, sonst die des Events bzw. des Termins der Mahlzeit
        $orgs = [$food->Parent()];
        $event = $food->Event();
        if ($event->exists()) {
            $orgs[] = $event->Organization();
        }
        foreach ($food->Meals() as $meal) {
            $appointment = $meal->Parent();
            if ($appointment && $appointment->exists()) {
                array_push($orgs, ...$appointment->Organisations()->toArray());
            }
        }

        self::deliver(
            [$food->SupplierID],
            self::orgIDs(...$orgs),
            'meals',
            $accepted ? '✅ Vorschlag bestätigt' : '❌ Vorschlag abgelehnt',
            'Dein Vorschlag "' . $food->Title . '" wurde ' . ($accepted ? 'bestätigt' : 'abgelehnt') . '.',
            '/app/food'
        );
    }

    // ── Inventar ─────────────────────────────────────────────────────────────

    /**
     * Neuer Ausleih-Antrag: bei Org-Inventar alle Mitglieder mit INVENTORY_APPROVE_RENTALS
     * (außer der antragstellenden Person), bei privatem Equipment nur dessen Besitzer.
     */
    public static function notifyRentalRequested(InventoryRental $rental): void
    {
        // Genehmiger der verleihenden Organisation (bei freigegebenen Objekten
        // nicht unbedingt die Organisation, für die ausgeliehen wird)
        $org = $rental->getSourceOrganization();
        if (!$rental->isPrivateLending() && !$org) {
            return;
        }

        $requester = $rental->Member();
        $title     = '📦 Neuer Ausleih-Antrag';
        $body      = ($requester && $requester->exists() ? $requester->getDisplayName() : 'Jemand')
            . ' möchte ' . $rental->getContentSummary() . ' ausleihen ('
            . self::formatDateRange($rental->StartDate, $rental->EndDate) . ').';
        $url       = '/app/inventory/rentals/' . $rental->ID;

        // Privates Equipment: nur der Besitzer entscheidet und wird benachrichtigt
        if ($rental->isPrivateLending()) {
            $body = str_replace(' ausleihen (', ' von dir ausleihen (', $body);
            if ($rental->isPrivateUse()) {
                $body = rtrim($body, '.') . ' für private Zwecke.';
            }
            self::deliver([$rental->LenderID], null, 'inventory', $title, $body, $url);
            return;
        }

        // Ausleihe für private Zwecke bzw. durch eine andere Organisation (freigegebene Objekte)
        $context = $rental->Organization();
        if ($rental->isPrivateUse()) {
            $body = rtrim($body, '.') . ' für private Zwecke.';
        } elseif ($context && $context->exists() && (int) $context->ID !== (int) $org->ID) {
            $body = rtrim($body, '.') . ' für ' . $context->Title . '.';
        }

        self::deliver(
            self::orgMemberIDsWithPermission($org, OrgPermissions::INVENTORY_APPROVE_RENTALS),
            self::orgIDs($org),
            'inventory',
            $title,
            $body,
            $url,
            [$rental->MemberID]
        );
    }

    /**
     * Gemeldeter Schaden: bei privatem Equipment an den Besitzer, sonst an die Genehmiger
     * der verleihenden Organisation (jeweils nicht die meldende Person selbst).
     */
    public static function notifyDamageReported(InventoryDamageReport $report): void
    {
        $rental = $report->Rental();
        if (!$rental->exists()) {
            return;
        }

        $reporter = $report->ReportedBy();
        $private = $rental->isPrivateLending();
        $org = $private ? null : $rental->getSourceOrganization();

        self::deliver(
            $private ? [$rental->LenderID] : self::orgMemberIDsWithPermission($org, OrgPermissions::INVENTORY_APPROVE_RENTALS),
            $private ? null : self::orgIDs($org),
            'inventory',
            $report->MakesUnusable ? '⚠️ Schaden gemeldet – unbenutzbar' : '⚠️ Schaden gemeldet',
            ($reporter->exists() ? $reporter->getDisplayName() : 'Jemand') . ' hat einen Schaden an „'
                . $report->getTargetTitle() . '“ gemeldet.',
            '/app/inventory/rentals/' . $rental->ID,
            [$report->ReportedByID]
        );
    }

    /** Genehmigung oder Ablehnung — an die antragstellende Person */
    public static function notifyRentalDecision(InventoryRental $rental): void
    {
        $approved = $rental->Status === 'approved';
        $body     = 'Deine Ausleihe (' . self::formatDateRange($rental->StartDate, $rental->EndDate) . ') wurde '
            . ($approved ? 'genehmigt' : 'abgelehnt') . '.';
        if ($approved && $rental->UsageCondition !== 'free') {
            $body .= ' Auflage: ' . (InventoryRental::CONDITION_LABELS[$rental->UsageCondition] ?? $rental->UsageCondition) . '.';
        }

        // Privat verliehenes Equipment ist persönlich; sonst muss die Person der verleihenden
        // oder der ausleihenden Organisation angehören
        self::deliver(
            [$rental->MemberID],
            $rental->isPrivateLending() ? null : self::orgIDs($rental->getSourceOrganization(), $rental->Organization()),
            'inventory',
            $approved ? '✅ Ausleihe genehmigt' : '❌ Ausleihe abgelehnt',
            $body,
            '/app/inventory/rentals/' . $rental->ID
        );
    }

    private static function formatDateRange(?string $start, ?string $end): string
    {
        $format = fn (?string $date) => $date ? date('d.m.Y', strtotime($date)) : '?';
        return $start === $end ? $format($start) : $format($start) . ' – ' . $format($end);
    }

    // ── Firebase Cloud Messaging ─────────────────────────────────────────────

    /**
     * Send actual FCM notification. Ungültige Tokens (App deinstalliert, Berechtigung
     * entzogen, abgelaufen) werden dabei aus der DB entfernt.
     */
    private static function sendNotification($token, $title, $body, $url = null): bool
    {
        $accessToken = self::getCachedAccessToken();
        $projectId = self::getProjectId();

        if (!$accessToken || !$projectId) {
            error_log('FCM: kein Access-Token oder keine Projekt-ID — Push nicht gesendet');
            return false;
        }

        // Data-only, damit nur der Service Worker die Benachrichtigung anzeigt (keine
        // Duplikate). FCM verlangt dabei ausschließlich String-Werte.
        $data = [
            'message' => [
                'token' => $token,
                'data' => [
                    'title' => (string) $title,
                    'body' => (string) $body,
                    'url' => (string) ($url ?: '/app/dashboard')
                ],
                // Ohne "high" stellen mobile Browser Pushes im Energiesparmodus u. U.
                // stark verzögert zu; nach einem Tag ist die Benachrichtigung hinfällig
                'webpush' => [
                    'headers' => [
                        'Urgency' => 'high',
                        'TTL' => '86400'
                    ]
                ]
            ]
        ];

        [$httpCode, $result] = self::postMessage($projectId, $accessToken, $data);

        // Access-Token abgelaufen (lange laufender Prozess) — einmal neu holen
        if ($httpCode === 401) {
            self::$cachedAccessToken = null;
            $accessToken = self::getCachedAccessToken();
            if ($accessToken) {
                [$httpCode, $result] = self::postMessage($projectId, $accessToken, $data);
            }
        }

        if ($httpCode === 200) {
            return true;
        }

        if (self::isInvalidTokenError($httpCode, (string) $result)) {
            NotificationToken::get()->filter('Token', $token)->removeAll();
            return false;
        }

        error_log('FCM Error (HTTP ' . $httpCode . '): ' . $result);
        return false;
    }

    private static function postMessage(string $projectId, string $accessToken, array $data): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($result === false) {
            $result = 'cURL: ' . curl_error($ch);
        }

        return [$httpCode, $result];
    }

    /**
     * Token existiert bei FCM nicht (mehr) bzw. gehört zu einem anderen Projekt
     */
    private static function isInvalidTokenError(int $httpCode, string $result): bool
    {
        if ($httpCode === 404 || str_contains($result, 'UNREGISTERED')) {
            return true;
        }
        if ($httpCode === 403 && str_contains($result, 'SENDER_ID_MISMATCH')) {
            return true;
        }
        return $httpCode === 400
            && str_contains($result, 'INVALID_ARGUMENT')
            && str_contains($result, 'registration token');
    }

    /** Ob der Server überhaupt Pushes verschicken kann (Service-Account vorhanden) */
    public static function isConfigured(): bool
    {
        return self::getServiceAccount() !== null && self::getProjectId();
    }

    private static function getProjectId(): ?string
    {
        return Environment::getEnv('VITE_FIREBASE_PROJECT_ID')
            ?: (self::getServiceAccount()['project_id'] ?? null);
    }

    private static function getServiceAccount(): ?array
    {
        $serviceAccountPath = Environment::getEnv('FIREBASE_SERVICE_ACCOUNT_PATH')
            ?: BASE_PATH . '/firebase-service-account.json';

        if (!file_exists($serviceAccountPath)) {
            error_log('Firebase service account file not found at: ' . $serviceAccountPath);
            return null;
        }

        $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

        if (!$serviceAccount) {
            error_log('Failed to parse service account JSON');
            return null;
        }

        return $serviceAccount;
    }

    /**
     * Access-Token wiederverwenden, bis kurz vor Ablauf (gültig 1 Stunde)
     */
    private static function getCachedAccessToken(): ?string
    {
        if (!self::$cachedAccessToken || time() >= self::$cachedAccessTokenExpires) {
            self::$cachedAccessToken = self::getAccessToken();
            self::$cachedAccessTokenExpires = time() + 3000;
        }

        return self::$cachedAccessToken;
    }

    /**
     * Get OAuth2 access token for FCM using service account
     */
    private static function getAccessToken(): ?string
    {
        $serviceAccount = self::getServiceAccount();

        if (!$serviceAccount) {
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

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            error_log('Failed to get OAuth2 access token. Response: ' . $response);
            return null;
        }

        return $data['access_token'];
    }
}
