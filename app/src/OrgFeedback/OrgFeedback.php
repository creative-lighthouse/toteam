<?php

namespace App\OrgFeedback;

use App\Tasks\Task;
use App\Teams\Organization;
use App\Teams\OrgEvent;
use App\Teams\OrgPermissions;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\OrgFeedback\OrgFeedback
 *
 * Feedback im Feedback-Totem — zu einem Event oder allgemein zur Organisation. Eine reine
 * Sammlung: jede:r Mitglied kann Feedback geben, sich per "+1" anschließen
 * ({@see OrgFeedbackVote}) und kommentieren ({@see OrgFeedbackComment}). Feedback ist nie
 * anonym. Nicht zu verwechseln mit {@see \App\Feedback\Feedback} (Fehlermeldungen an die
 * Entwickler).
 *
 * @property ?string $Title
 * @property ?string $Content
 * @property ?string $Sentiment
 * @property int $OrganizationID
 * @property int $EventID
 * @property int $AuthorID
 * @property int $LinkedTaskID
 * @method \App\Teams\Organization Organization()
 * @method \App\Teams\OrgEvent Event()
 * @method \SilverStripe\Security\Member Author()
 * @method \App\Tasks\Task LinkedTask()
 * @method \SilverStripe\ORM\HasManyList|\App\OrgFeedback\OrgFeedbackVote[] Votes()
 * @method \SilverStripe\ORM\HasManyList|\App\OrgFeedback\OrgFeedbackComment[] Comments()
 */
class OrgFeedback extends DataObject
{
    public const SENTIMENTS = ['Positive', 'Neutral', 'Negative'];

    /** Formatierung wie im Skript-Editor: fett/kursiv/unterstrichen, Überschriften, Listen */
    public const ALLOWED_TAGS = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li'];

    private static $db = [
        "Title"     => "Varchar(255)",
        "Content"   => "HTMLText",
        "Sentiment" => "Enum('Positive,Neutral,Negative','Neutral')",
    ];

    private static $has_one = [
        "Organization" => Organization::class,
        // Optional — ohne Event ist es allgemeines Feedback zur Organisation
        "Event"        => OrgEvent::class,
        "Author"       => Member::class,
        // Aus dem Feedback erstellte Aufgabe
        "LinkedTask"   => Task::class,
    ];

    private static $has_many = [
        "Votes"    => OrgFeedbackVote::class . '.Feedback',
        "Comments" => OrgFeedbackComment::class . '.Feedback',
    ];

    private static $cascade_deletes = [
        "Votes",
        "Comments",
    ];

    private static $default_sort = "Created DESC";

    private static $field_labels = [
        "Title"        => "Titel",
        "Content"      => "Text",
        "Sentiment"    => "Art",
        "Organization" => "Organisation",
        "Event"        => "Event",
        "Author"       => "Verfasser",
        "LinkedTask"   => "Aufgabe",
    ];

    private static $summary_fields = [
        "Title"              => "Titel",
        "Sentiment"          => "Art",
        "Organization.Title" => "Organisation",
        "Author.Title"       => "Verfasser",
        "Created"            => "Eingereicht",
    ];

    private static $table_name = 'OrgFeedback';
    private static $singular_name = "Feedback";
    private static $plural_name = "Feedback";

    /** Absätze und Überschriften dürfen zentriert oder rechtsbündig sein (links ist Standard) */
    public const ALIGNABLE_TAGS = ['p', 'h2', 'h3', 'h4'];

    /**
     * Allowlist-Bereinigung: nur ALLOWED_TAGS bleiben, und zwar ohne jedes Attribut — einzige
     * Ausnahme ist `style="text-align: center|right"` an ALIGNABLE_TAGS (neu geschrieben).
     * Der Client ist nicht vertrauenswürdig, das Frontend rendert den Inhalt als HTML.
     */
    public static function sanitizeContent(string $html): string
    {
        // Inhalt von script/style ganz entfernen, nicht nur die Tags
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? '';
        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $stripped = strip_tags($html, $allowed);
        $tags = implode('|', self::ALLOWED_TAGS);
        return preg_replace_callback('#<(/?)(' . $tags . ')\b([^>]*)>#i', function (array $m): string {
            $tag = strtolower($m[2]);
            if ($m[1] === ''
                && in_array($tag, self::ALIGNABLE_TAGS, true)
                && preg_match('#text-align\s*:\s*(center|right)\b#i', $m[3], $align)
            ) {
                return '<' . $tag . ' style="text-align: ' . strtolower($align[1]) . '">';
            }
            return '<' . $m[1] . $tag . '>';
        }, $stripped) ?? '';
    }

    /** Der reine Text (für Leer-Prüfung, Aufgaben-Beschreibung und Benachrichtigungen) */
    public static function plainText(string $html): string
    {
        $text = preg_replace('#</(p|h[234]|li)>\s*|<br\s*/?>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /** Das Event, falls es (noch) existiert */
    public function getEventOrNull(): ?OrgEvent
    {
        $event = $this->EventID ? $this->Event() : null;
        return $event && $event->exists() ? $event : null;
    }

    /** Sehen dürfen alle Mitglieder der Organisation */
    public function isViewableBy(?Member $member): bool
    {
        return $member && in_array((int) $this->OrganizationID, array_map('intval', $member->getOrganizationIDs()), true);
    }

    /** Bearbeiten darf nur, wer es geschrieben hat (und noch Mitglied ist) */
    public function isEditableBy(?Member $member): bool
    {
        return $member && (int) $this->AuthorID === (int) $member->ID && $this->isViewableBy($member);
    }

    /** Löschen: der Verfasser oder wer in der Organisation Feedback moderieren darf */
    public function isDeletableBy(?Member $member): bool
    {
        return $this->isEditableBy($member) || $this->canModerate($member);
    }

    public function canModerate(?Member $member): bool
    {
        $org = $this->Organization();
        return $member && $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::FEEDBACK_MODERATE);
    }

    /** Zur Aufgabe machen darf, wer in der Organisation Aufgaben erstellen darf */
    public function canConvertToTask(?Member $member): bool
    {
        $org = $this->Organization();
        return $member && $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::TASKS_CREATE);
    }

    /** Pfad im Frontend: die Liste öffnet das Detail-Modal über ?id= */
    public function Link(): string
    {
        return '/app/feedback?id=' . $this->ID;
    }

    public function canView($member = null)
    {
        return parent::canView($member) || $this->isViewableBy($member);
    }
}
