<?php

namespace App\OrgFeedback;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\OrgFeedback\OrgFeedbackCommentLike
 *
 * "Daumen hoch" eines Mitglieds zu einem Feedback-Kommentar — höchstens einer pro Person
 * und Kommentar; ein erneuter Klick nimmt ihn zurück (der Datensatz wird gelöscht).
 *
 * @property int $CommentID
 * @property int $MemberID
 * @method \App\OrgFeedback\OrgFeedbackComment Comment()
 * @method \SilverStripe\Security\Member Member()
 */
class OrgFeedbackCommentLike extends DataObject
{
    private static $has_one = [
        "Comment" => OrgFeedbackComment::class,
        "Member"  => Member::class,
    ];

    private static $indexes = [
        "CommentMember" => [
            "type"    => "unique",
            "columns" => ["CommentID", "MemberID"],
        ],
    ];

    private static $field_labels = [
        "Comment" => "Kommentar",
        "Member"  => "Mitglied",
    ];

    private static $table_name = 'OrgFeedbackCommentLike';
    private static $singular_name = "Kommentar-Daumen";
    private static $plural_name = "Kommentar-Daumen";
}
