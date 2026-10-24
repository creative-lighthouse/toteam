<?php

namespace App\OrgFeedback;

/**
 * Findet Feedback mit ähnlichem Titel — für den Hinweis "Ähnliches Feedback gibt es schon"
 * beim Anlegen. Bewusst einfach: Titel werden normalisiert (Kleinschreibung, Umlaute,
 * Satzzeichen, Füllwörter raus) und über die gemeinsamen Wörter verglichen; Wörter gelten
 * auch bei Tippfehlern oder Wortendungen als gleich ("Toiletten" ≈ "Toilette").
 */
class OrgFeedbackSimilarity
{
    /** Ab diesem Wert (0–1) gilt ein Titel als ähnlich */
    private const THRESHOLD = 0.5;

    private const STOP_WORDS = [
        'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines',
        'und', 'oder', 'aber', 'zu', 'zum', 'zur', 'im', 'in', 'am', 'an', 'auf', 'aus', 'bei', 'mit',
        'von', 'vom', 'fuer', 'ist', 'sind', 'war', 'waren', 'wird', 'werden', 'hat', 'haben', 'es',
        'nicht', 'kein', 'keine', 'sehr', 'so', 'wie', 'als', 'auch', 'noch', 'mehr', 'man',
        'wir', 'ich', 'mal', 'bitte', 'beim', 'nach', 'vor', 'ueber', 'unter', 'fur', 'uber',
    ];

    /**
     * @param iterable<OrgFeedback> $candidates
     * @return array<int, array{feedback: OrgFeedback, score: float}> die besten Treffer, absteigend
     */
    public static function findSimilar(string $title, iterable $candidates, int $limit = 3): array
    {
        $words = self::words($title);
        if (!$words) {
            return [];
        }

        $matches = [];
        foreach ($candidates as $candidate) {
            $score = self::score($words, self::words((string) $candidate->Title));
            if ($score >= self::THRESHOLD) {
                $matches[] = ['feedback' => $candidate, 'score' => $score];
            }
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($matches, 0, $limit);
    }

    /** Normalisierte, aussagekräftige Wörter eines Titels */
    public static function words(string $title): array
    {
        $text = mb_strtolower($title);
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? '';

        $words = array_filter(
            explode(' ', $text),
            fn ($w) => strlen($w) > 1 && !in_array($w, self::STOP_WORDS, true)
        );
        return array_values(array_unique($words));
    }

    /**
     * Anteil der zusammenpassenden Wörter, gemessen am kürzeren Titel — so wird
     * "Toiletten dreckig" auch in "Die Toiletten waren am Samstag dreckig" gefunden.
     * Bei nur einem Wort auf einer Seite zählt zusätzlich die Länge des längeren Titels,
     * damit ein einzelnes Allerweltswort nicht alles trifft.
     */
    private static function score(array $a, array $b): float
    {
        if (!$a || !$b) {
            return 0.0;
        }

        $matched = 0;
        $used = [];
        foreach ($a as $wordA) {
            foreach ($b as $i => $wordB) {
                if (!isset($used[$i]) && self::wordsMatch($wordA, $wordB)) {
                    $used[$i] = true;
                    $matched++;
                    break;
                }
            }
        }

        $shorter = min(count($a), count($b));
        $longer = max(count($a), count($b));
        $score = $matched / $shorter;

        if ($shorter === 1 && $longer > 2) {
            $score *= 2 / $longer;
        }
        return $score;
    }

    /** Gleich, gleicher Wortstamm oder höchstens ein Tippfehler pro 4 Zeichen */
    private static function wordsMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $min = min(strlen($a), strlen($b));
        if ($min < 4) {
            return false;
        }

        // Gleicher Anfang: "toilette"/"toiletten", "essen"/"essens"
        $prefix = max(4, $min - 2);
        if (strncmp($a, $b, $prefix) === 0 && abs(strlen($a) - strlen($b)) <= 3) {
            return true;
        }

        return levenshtein($a, $b) <= intdiv($min, 4);
    }
}
