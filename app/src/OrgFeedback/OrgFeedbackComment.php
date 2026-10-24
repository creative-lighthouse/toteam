<?php

namespace App\OrgFeedback;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\OrgFeedback\OrgFeedbackComment
 *
 * Kommentar zu einem Feedback (reiner Text), geschrieben im Detail-Modal. Andere können
 * einen Daumen hoch geben ({@see OrgFeedbackCommentLike}).
 *
 * @property ?string $Content
 * @property int $FeedbackID
 * @property int $AuthorID
 * @method \App\OrgFeedback\OrgFeedback Feedback()
 * @method \SilverStripe\Security\Member Author()
 * @method \SilverStripe\ORM\HasManyList|\App\OrgFeedback\OrgFeedbackCommentLike[] Likes()
 */
class OrgFeedbackComment extends DataObject
{
    private static $db = [
        "Content" => "Text",
    ];

    private static $has_one = [
        "Feedback" => OrgFeedback::class,
        "Author"   => Member::class,
    ];

    private static $has_many = [
        "Likes" => OrgFeedbackCommentLike::class . '.Comment',
    ];

    private static $cascade_deletes = [
        "Likes",
    ];

    private static $default_sort = "Created ASC";

    private static $field_labels = [
        "Content"  => "Kommentar",
        "Feedback" => "Feedback",
        "Author"   => "Verfasser",
    ];

    private static $table_name = 'OrgFeedbackComment';
    private static $singular_name = "Feedback-Kommentar";
    private static $plural_name = "Feedback-Kommentare";

    /** Löschen: wer ihn geschrieben hat oder wer Feedback moderieren darf */
    public function isDeletableBy(?Member $member): bool
    {
        if (!$member) {
            return false;
        }
        $feedback = $this->Feedback();
        return ((int) $this->AuthorID === (int) $member->ID && $feedback->isViewableBy($member))
            || $feedback->canModerate($member);
    }
}
