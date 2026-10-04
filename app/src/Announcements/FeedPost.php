<?php

namespace App\Announcements;

use App\Notifications\PendingNotificationJob;
use App\Teams\OrgEvent;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;

/**
 * Class \App\Announcements\FeedPost
 *
 * Beitrag im Feed des Mitteilungs-Totems — vorerst nur Text, leicht formatiert (fett,
 * kursiv, unterstrichen; als HTML mit enger Allowlist, siehe sanitizeContent()). Gepostet wird als Person
 * oder (mit ANNOUNCEMENTS_CREATE) im Namen einer Organisation (Organization). Öffentliche
 * Beiträge sieht jede angemeldete Person in ToTeam, interne nur die Mitglieder von
 * InternalOrganization (beim Posten als Organisation ist das die Organisation selbst).
 * Mit @Benutzername markierte Personen werden in der Ausgabe verlinkt (siehe mentionedMembers()).
 * Mit Event (EventID) ist der Beitrag ein geteiltes Event: der Text (dann optional) steht im
 * Feed über der Event-Karte. Interne Events lassen sich nur intern für ihre Organisation teilen.
 * Beiträge im Namen einer Organisation benachrichtigen deren Mitglieder per Push (wie früher
 * die Mitteilungen, die der Feed ersetzt hat) — geplante erst zum Veröffentlichungszeitpunkt.
 *
 * @property ?string $Content
 * @property ?string $Visibility
 * @property ?string $ReleaseDate
 * @property ?string $ExpiryDate
 * @property int $AuthorID
 * @property int $OrganizationID
 * @property int $InternalOrganizationID
 * @property int $EventID
 * @method \SilverStripe\Security\Member Author()
 * @method \App\Teams\Organization Organization()
 * @method \App\Teams\Organization InternalOrganization()
 * @method \App\Teams\OrgEvent Event()
 */
class FeedPost extends DataObject
{
    public const VISIBILITY_PUBLIC = 'Public';
    public const VISIBILITY_INTERNAL = 'Internal';

    // Zeichen ohne Formatierung
    public const MAX_LENGTH = 2000;

    // Was der Editor (TipTap) erzeugt: Absätze, Zeilenumbrüche, fett, kursiv, unterstrichen
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'u'];

    // @benutzername — darf nicht auf Punkt/Bindestrich enden ("@max." am Satzende)
    public const MENTION_PATTERN = '/@([A-Za-z0-9_](?:[A-Za-z0-9._-]*[A-Za-z0-9_])?)/';

    private static $db = [
        "Content"    => "Text",
        "Visibility" => "Enum('Public,Internal','Public')",
        // Geplant: erst ab diesem Zeitpunkt für andere sichtbar (leer = sofort)
        "ReleaseDate" => "Datetime",
        // Ab diesem Zeitpunkt nicht mehr im Feed, sondern unter "Vergangene" (leer = bleibt)
        "ExpiryDate"  => "Datetime",
    ];

    private static $has_one = [
        "Author"               => Member::class,
        // Gepostet im Namen dieser Organisation (leer = als Person)
        "Organization"         => Organization::class,
        // Bei internen Beiträgen: für wen sichtbar
        "InternalOrganization" => Organization::class,
        // Geteiltes Event (optional)
        "Event"                => OrgEvent::class,
    ];

    private static $default_sort = "Created DESC";

    private static $field_labels = [
        "Content"              => "Text",
        "Visibility"           => "Sichtbarkeit",
        "ReleaseDate"          => "Veröffentlichen am",
        "ExpiryDate"           => "Ausblenden am",
        "Author"               => "Verfasst von",
        "Organization"         => "Im Namen von",
        "InternalOrganization" => "Intern für",
        "Event"                => "Geteiltes Event",
    ];

    private static $summary_fields = [
        "Created"           => "Erstellt",
        "Author.Name"       => "Verfasst von",
        "Visibility"        => "Sichtbarkeit",
    ];

    private static $table_name = 'FeedPost';
    private static $singular_name = "Feed-Beitrag";
    private static $plural_name = "Feed-Beiträge";

    /** Nur beim ersten Speichern benachrichtigen */
    private bool $notifyAfterWrite = false;

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Content = self::sanitizeContent((string) $this->Content);
        $this->notifyAfterWrite = !$this->isInDB() && $this->OrganizationID;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->notifyAfterWrite) {
            $this->notifyAfterWrite = false;
            PendingNotificationJob::create([
                'SourceClass' => self::class,
                'SourceID'    => $this->ID,
                'EventType'   => 'new_feed_post',
            ])->write();
        }
    }

    /** Detailseite im Frontend */
    public function getLink(): string
    {
        return '/app/announcements/' . $this->ID;
    }

    /** Kurzer reiner Text, z.B. für Push-Benachrichtigungen */
    public function getExcerpt(int $length = 120): string
    {
        $text = preg_replace('/\s+/', ' ', self::plainText((string) $this->Content)) ?? '';
        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }

    /**
     * Allowlist-Bereinigung: nur ALLOWED_TAGS bleiben, und zwar ohne jedes Attribut.
     * Der Client ist nicht vertrauenswürdig, das Frontend rendert den Inhalt als HTML.
     */
    public static function sanitizeContent(string $html): string
    {
        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $stripped = strip_tags($html, $allowed);
        $tags = implode('|', self::ALLOWED_TAGS);
        return preg_replace('#<(/?)(' . $tags . ')\b[^>]*>#i', '<$1$2>', $stripped) ?? '';
    }

    /** Der reine Text (für Länge, Leer-Prüfung und @-Markierungen) */
    public static function plainText(string $html): string
    {
        $text = preg_replace('#</p>\s*<p>|<br\s*/?>#i', "\n", $html) ?? $html;
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Beiträge, die $member sehen darf: öffentliche, interne der eigenen Organisationen
     * und die eigenen (geplante nur für Verfasser) — aktuelle nach Veröffentlichung,
     * abgelaufene ($archive) nach Ablauf sortiert, die neuesten zuerst.
     * @return FeedPost[]
     */
    public static function visibleTo(Member $member, bool $archive = false, int $limit = 100): array
    {
        $now = DBDatetime::now()->Rfc2822();
        $posts = self::get()->filterAny([
            'Visibility'             => self::VISIBILITY_PUBLIC,
            'InternalOrganizationID' => $member->getOrganizationIDs() ?: [-1],
            'AuthorID'               => $member->ID,
        ]);
        $posts = $archive
            ? $posts->filter('ExpiryDate:LessThanOrEqual', $now)->sort('ExpiryDate', 'DESC')
            : $posts->filterAny(['ExpiryDate' => null, 'ExpiryDate:GreaterThan' => $now])
                ->orderBy('COALESCE("FeedPost"."ReleaseDate", "FeedPost"."Created") DESC');

        $result = [];
        foreach ($posts->limit($limit) as $post) {
            // filterAny deckt weder "geplant" noch "intern, aber Organisation inaktiv" ab — daher genau prüfen
            if ($post->isViewableBy($member)) {
                $result[] = $post;
            }
        }
        return $result;
    }

    /** Geplant und noch nicht veröffentlicht */
    public function isScheduled(): bool
    {
        return $this->ReleaseDate && strtotime($this->ReleaseDate) > time();
    }

    /** Abgelaufen — steht nicht mehr im Feed, sondern im Archiv */
    public function isExpired(): bool
    {
        return $this->ExpiryDate && strtotime($this->ExpiryDate) <= time();
    }

    /** Zeitpunkt, ab dem der Beitrag erscheint (geplant oder erstellt) — für die Sortierung */
    public function getPublishedAt(): string
    {
        return $this->ReleaseDate ?: $this->Created;
    }

    public function isViewableBy(Member $member): bool
    {
        // Geplante Beiträge sehen vorab nur, wer sie verfasst hat bzw. für die Organisation posten darf
        if ($this->isScheduled()) {
            return $this->isDeletableBy($member);
        }
        if ((int) $this->AuthorID === (int) $member->ID || $this->Visibility === self::VISIBILITY_PUBLIC) {
            return true;
        }
        $org = $this->InternalOrganization();
        return $org && $org->exists() && $member->isActiveMemberOfOrg($org);
    }

    /** Löschen darf, wer den Beitrag verfasst hat — bei Organisations-Beiträgen auch, wer für die Organisation posten darf */
    public function isDeletableBy(Member $member): bool
    {
        if ((int) $this->AuthorID === (int) $member->ID) {
            return true;
        }
        $org = $this->Organization();
        return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::ANNOUNCEMENTS_CREATE);
    }

    /**
     * Im Text markierte Personen, die es gibt: Benutzername (klein) → Member
     * @return array<string, Member>
     */
    public function mentionedMembers(): array
    {
        preg_match_all(self::MENTION_PATTERN, self::plainText((string) $this->Content), $matches);
        $usernames = array_values(array_unique($matches[1] ?? []));
        if (!$usernames) {
            return [];
        }
        $result = [];
        foreach (Member::get()->filter('Username', $usernames) as $member) {
            $result[strtolower($member->Username)] = $member;
        }
        return $result;
    }

    private function eventToApi(Member $member): ?array
    {
        $event = $this->EventID ? $this->Event() : null;
        if (!$event || !$event->exists() || !$event->isViewableBy($member)) {
            return null;
        }
        return $event->isInternalFor($member)
            ? $event->toApiSummary($member)
            : array_merge($event->toApiPublic(), $event->interestToApi($member));
    }

    /** Für die API (Feed, Detailseite, Dashboard) aus Sicht von $member */
    public function toApi(Member $member): array
    {
        $author = $this->Author();
        $org = $this->Organization();
        $internalOrg = $this->InternalOrganization();

        // Nur existierende Benutzernamen werden im Frontend verlinkt
        $mentions = [];
        foreach ($this->mentionedMembers() as $key => $mentioned) {
            $mentions[$key] = ['ID' => $mentioned->ID, 'Name' => $mentioned->getDisplayName(), 'Username' => $mentioned->Username];
        }

        return [
            'ID'         => $this->ID,
            'Type'       => 'post',
            'Content'    => (string) $this->Content,
            'Visibility' => $this->Visibility,
            'Created'    => date('c', strtotime($this->Created)),
            // Zeitpunkt, ab dem der Beitrag erscheint — bei geplanten Beiträgen in der Zukunft
            'SortDate'   => date('c', strtotime($this->getPublishedAt())),
            'Status'     => $this->isScheduled() ? 'scheduled' : ($this->isExpired() ? 'expired' : 'active'),
            'ExpiryDate' => $this->ExpiryDate ? date('c', strtotime($this->ExpiryDate)) : null,
            'Author'     => $author && $author->exists() ? [
                'ID'       => $author->ID,
                'Name'     => $author->getDisplayName(),
                'Username' => $author->Username ?: null,
                'Avatar'   => $author->RenderProfileImage(),
            ] : null,
            'Organization' => $org && $org->exists() ? [
                'ID'       => $org->ID,
                'Title'    => $org->Title,
                'Username' => $org->Username ?: null,
                'LogoURL'  => $org->RenderLogo(80),
            ] : null,
            'InternalOrganization' => $internalOrg && $internalOrg->exists() ? [
                'ID'    => $internalOrg->ID,
                'Title' => $internalOrg->Title,
            ] : null,
            // Geteiltes Event aus Sicht von $member — null, wenn es (inzwischen) nicht sichtbar ist
            'Event'     => $this->eventToApi($member),
            'HasEvent'  => (bool) $this->EventID,
            'Mentions'  => (object) $mentions,
            'CanDelete' => $this->isDeletableBy($member),
        ];
    }
}
