<?php

namespace App\OrgFeedback;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\OrgFeedback\OrgFeedbackVote
 *
 * "+1" eines Mitglieds zu einem Feedback — höchstens eins pro Person und Feedback;
 * ein erneuter Klick nimmt es zurück (der Datensatz wird gelöscht).
 *
 * @property int $FeedbackID
 * @property int $MemberID
 * @method \App\OrgFeedback\OrgFeedback Feedback()
 * @method \SilverStripe\Security\Member Member()
 */
class OrgFeedbackVote extends DataObject
{
    private static $has_one = [
        "Feedback" => OrgFeedback::class,
        "Member"   => Member::class,
    ];

    private static $indexes = [
        "FeedbackMember" => [
            "type"    => "unique",
            "columns" => ["FeedbackID", "MemberID"],
        ],
    ];

    private static $field_labels = [
        "Feedback" => "Feedback",
        "Member"   => "Mitglied",
    ];

    private static $table_name = 'OrgFeedbackVote';
    private static $singular_name = "Feedback-Stimme";
    private static $plural_name = "Feedback-Stimmen";
}
