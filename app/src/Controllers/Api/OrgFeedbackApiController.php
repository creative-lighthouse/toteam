<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Notifications\PushNotificationService;
use App\OrgFeedback\OrgFeedback;
use App\OrgFeedback\OrgFeedbackComment;
use App\OrgFeedback\OrgFeedbackCommentLike;
use App\OrgFeedback\OrgFeedbackSimilarity;
use App\OrgFeedback\OrgFeedbackVote;
use App\Tasks\Task;
use App\Teams\Organization;
use App\Teams\OrgEvent;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Feedback-Totem: Feedback zu Events oder allgemein zur Organisation, "+1" und Kommentare.
 * Nicht zu verwechseln mit {@see FeedbackApiController} (Fehlermeldungen an die Entwickler).
 */
class OrgFeedbackApiController extends ApiController
{
    private static $url_segment = 'api/v1/orgfeedback';

    private static $allowed_actions = [
        'index',
        'detail',
        'store',
        'update',
        'remove',
        'vote',
        'comment',
        'commentRemove',
        'commentLike',
        'similar',
        'createTask',
        'eventFeedback',
    ];

    private const MAX_COMMENT_LENGTH = 5000;

    protected function getDefaultAction()
    {
        return 'index';
    }

    private function formatMember(?Member $member): ?array
    {
        if (!$member || !$member->exists()) {
            return null;
        }
        return [
            'ID'       => $member->ID,
            'Name'     => $member->getDisplayName(),
            'Avatar'   => $member->RenderProfileImage(),
            'Username' => $member->Username ?: null,
        ];
    }

    private function formatEvent(?OrgEvent $event): ?array
    {
        return $event ? [
            'ID'         => $event->ID,
            'Title'      => $event->Title,
            'URLSegment' => $event->URLSegment,
        ] : null;
    }

    /**
     * Zähler für mehrere Feedbacks auf einmal (statt einer Abfrage pro Eintrag)
     * @param int[] $ids
     * @return array{votes: array<int,int>, mine: array<int,bool>, comments: array<int,int>}
     */
    private function loadCounts(array $ids, Member $member): array
    {
        if (!$ids) {
            return ['votes' => [], 'mine' => [], 'comments' => []];
        }
        return [
            'votes'    => array_count_values(OrgFeedbackVote::get()->filter('FeedbackID', $ids)->column('FeedbackID')),
            'mine'     => array_fill_keys(OrgFeedbackVote::get()->filter(['FeedbackID' => $ids, 'MemberID' => $member->ID])->column('FeedbackID'), true),
            'comments' => array_count_values(OrgFeedbackComment::get()->filter('FeedbackID', $ids)->column('FeedbackID')),
        ];
    }

    /** Für die Liste: nur was die Zeile braucht (Titel, Art, +1, Kommentare, Event) */
    private function formatSummary(OrgFeedback $feedback, array $counts): array
    {
        $org = $feedback->Organization();
        return [
            'ID'           => $feedback->ID,
            'Title'        => $feedback->Title,
            'Sentiment'    => $feedback->Sentiment ?: 'Neutral',
            'Created'      => $feedback->Created,
            'VoteCount'    => $counts['votes'][$feedback->ID] ?? 0,
            'HasVoted'     => $counts['mine'][$feedback->ID] ?? false,
            'CommentCount' => $counts['comments'][$feedback->ID] ?? 0,
            'Event'        => $this->formatEvent($feedback->getEventOrNull()),
            'Organization' => $org && $org->exists() ? ['ID' => $org->ID, 'Title' => $org->Title] : null,
        ];
    }

    /** Für das Detail-Modal: alles */
    private function formatDetail(OrgFeedback $feedback, Member $member): array
    {
        $commentList = $feedback->Comments();
        $likes = $this->loadCommentLikes($commentList->column('ID'));
        $comments = [];
        foreach ($commentList as $comment) {
            $comments[] = $this->formatComment($comment, $member, $likes[$comment->ID] ?? []);
        }

        $voters = [];
        foreach ($feedback->Votes()->sort('Created ASC') as $vote) {
            if ($formatted = $this->formatMember($vote->Member())) {
                $voters[] = $formatted + ['VotedAt' => $vote->Created];
            }
        }

        $task = $feedback->LinkedTaskID ? $feedback->LinkedTask() : null;

        return array_merge(
            $this->formatSummary($feedback, $this->loadCounts([$feedback->ID], $member)),
            [
                'Content'          => (string) $feedback->Content,
                'Author'           => $this->formatMember($feedback->Author()),
                'Voters'           => $voters,
                'Comments'         => $comments,
                'LinkedTask'       => $task && $task->exists() && $task->isViewableBy($member)
                    ? ['ID' => $task->ID, 'Hash' => $task->Hash, 'Title' => $task->Title]
                    : null,
                'CanEdit'          => $feedback->isEditableBy($member),
                'CanDelete'        => $feedback->isDeletableBy($member),
                'CanConvertToTask' => !($task && $task->exists()) && $this->tasksEnabled($feedback) && $feedback->canConvertToTask($member),
            ]
        );
    }

    /**
     * Daumen-hoch-Namen je Kommentar in einer Abfrage: [CommentID => [MemberID => Name]],
     * in der Reihenfolge, in der sie gegeben wurden
     */
    private function loadCommentLikes(array $commentIDs): array
    {
        if (!$commentIDs) {
            return [];
        }
        $likes = [];
        $list = OrgFeedbackCommentLike::get()->filter('CommentID', $commentIDs)->sort('Created ASC');
        foreach ($list as $like) {
            $liker = $like->Member();
            if ($liker && $liker->exists()) {
                $likes[(int) $like->CommentID][(int) $like->MemberID] = $liker->getDisplayName();
            }
        }
        return $likes;
    }

    /** @param array $likes [MemberID => Name] der Daumen hoch zu diesem Kommentar */
    private function formatComment(OrgFeedbackComment $comment, Member $member, array $likes = []): array
    {
        return [
            'ID'        => $comment->ID,
            'Content'   => $comment->Content,
            'Created'   => $comment->Created,
            'Author'    => $this->formatMember($comment->Author()),
            'CanDelete' => $comment->isDeletableBy($member),
            'LikeCount' => count($likes),
            'HasLiked'  => isset($likes[(int) $member->ID]),
            'Likers'    => array_values($likes),
        ];
    }

    private function tasksEnabled(OrgFeedback $feedback): bool
    {
        $org = $feedback->Organization();
        return $org && $org->exists() && (bool) $org->EnableTasks;
    }

    /** Organisationen des Nutzers mit aktivem Feedback-Totem */
    private function feedbackOrgs(Member $member): array
    {
        $ids = $member->getOrganizationIDs();
        if (!$ids) {
            return [];
        }
        return Organization::get()->filter(['ID' => $ids, 'EnableFeedback' => true])->sort('Title')->toArray();
    }

    /** Feedback per ID, sofern der Nutzer es sehen darf */
    private function findFeedback(HTTPRequest $request, Member $member): ?OrgFeedback
    {
        $feedback = OrgFeedback::get()->byID((int) $request->param('ID'));
        return $feedback && $feedback->isViewableBy($member) ? $feedback : null;
    }

    /**
     * Organisation und Event aus den Eingaben: mit Event gilt dessen Organisation.
     * @return array{0: ?Organization, 1: ?OrgEvent, 2: ?string} [Org, Event, Fehler]
     */
    private function resolveTarget(array $body, Member $member): array
    {
        $event = null;
        if ($eventID = (int) ($body['EventID'] ?? 0)) {
            $event = OrgEvent::get()->byID($eventID);
            if (!$event || !$event->isInternalFor($member)) {
                return [null, null, 'Event nicht gefunden'];
            }
        }

        $orgID = $event ? (int) $event->OrganizationID : (int) ($body['OrganizationID'] ?? 0);
        $org = $orgID ? Organization::get()->byID($orgID) : null;
        if (!$org || !$member->isActiveMemberOfOrg($org)) {
            return [null, null, 'Bitte eine Organisation wählen, in der du Mitglied bist'];
        }
        if (!$org->EnableFeedback) {
            return [null, null, 'Das Feedback-Totem ist für diese Organisation nicht aktiv'];
        }
        return [$org, $event, null];
    }

    /** @return array{0: ?array, 1: ?string} [Felder, Fehler] */
    private function validateFields(array $body): array
    {
        $title = trim((string) ($body['Title'] ?? ''));
        if ($title === '') {
            return [null, 'Titel ist erforderlich'];
        }
        if (mb_strlen($title) > 255) {
            return [null, 'Der Titel darf höchstens 255 Zeichen lang sein'];
        }

        $content = OrgFeedback::sanitizeContent((string) ($body['Content'] ?? ''));
        if (OrgFeedback::plainText($content) === '') {
            $content = '';
        }

        $sentiment = $body['Sentiment'] ?? '';
        if (!in_array($sentiment, OrgFeedback::SENTIMENTS, true)) {
            return [null, 'Bitte die Art des Feedbacks wählen'];
        }

        return [['Title' => $title, 'Content' => $content, 'Sentiment' => $sentiment], null];
    }

    /**
     * GET /api/v1/orgfeedback?organization=ID&event=ID|general&sort=votes|newest
     * Liste (nur Kurzform) plus die Organisationen und Events für Filter und Formular.
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgs = $this->feedbackOrgs($member);
        $orgIDs = array_map(fn (Organization $o) => (int) $o->ID, $orgs);

        $list = OrgFeedback::get()->filter('OrganizationID', $orgIDs ?: [0]);
        if ($orgID = (int) $request->getVar('organization')) {
            $list = $list->filter('OrganizationID', $orgID);
        }
        $eventFilter = $request->getVar('event');
        if ($eventFilter === 'general') {
            $list = $list->filter('EventID', 0);
        } elseif ($eventID = (int) $eventFilter) {
            $list = $list->filter('EventID', $eventID);
        }

        $records = $list->toArray();
        $counts = $this->loadCounts(array_map(fn (OrgFeedback $f) => (int) $f->ID, $records), $member);
        $data = array_map(fn (OrgFeedback $f) => $this->formatSummary($f, $counts), $records);

        if ($request->getVar('sort') !== 'newest') {
            usort($data, fn ($a, $b) => [$b['VoteCount'], $b['Created']] <=> [$a['VoteCount'], $a['Created']]);
        }

        $events = [];
        if ($orgIDs) {
            foreach (OrgEvent::get()->filter('OrganizationID', $orgIDs)->sort(['DateStart' => 'DESC', 'Title' => 'ASC']) as $event) {
                $events[] = [
                    'ID'             => $event->ID,
                    'Title'          => $event->Title,
                    'OrganizationID' => (int) $event->OrganizationID,
                    'DateStart'      => $event->DateStart ?: null,
                ];
            }
        }

        return $this->jsonResponse([
            'feedback'      => $data,
            'organizations' => array_map(fn (Organization $o) => [
                'ID'      => $o->ID,
                'Title'   => $o->Title,
                'LogoURL' => $o->RenderLogo(40),
            ], $orgs),
            'events'        => $events,
        ]);
    }

    /** GET /api/v1/orgfeedback/detail/{id} */
    public function detail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $feedback = $this->findFeedback($request, $member);
        if (!$feedback) {
            return $this->errorResponse('Feedback nicht gefunden', 404);
        }

        return $this->successResponse(['feedback' => $this->formatDetail($feedback, $member)]);
    }

    /** POST /api/v1/orgfeedback/store — Title, Content, Sentiment, OrganizationID, EventID */
    public function store(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        [$fields, $error] = $this->validateFields($body);
        if ($error) {
            return $this->errorResponse($error, 400);
        }
        [$org, $event, $error] = $this->resolveTarget($body, $member);
        if ($error) {
            return $this->errorResponse($error, 400);
        }

        $feedback = OrgFeedback::create($fields);
        $feedback->OrganizationID = $org->ID;
        $feedback->EventID = $event ? $event->ID : 0;
        $feedback->AuthorID = $member->ID;
        $feedback->write();

        return $this->successResponse(['feedback' => $this->formatDetail($feedback, $member)]);
    }

    /** PUT /api/v1/orgfeedback/update/{id} — nur der Verfasser */
    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $feedback = $this->findFeedback($request, $member);
        if (!$feedback) {
            return $this->errorResponse('Feedback nicht gefunden', 404);
        }
        if (!$feedback->isEditableBy($member)) {
            return $this->errorResponse('Nur wer das Feedback geschrieben hat, kann es bearbeiten', 403);
        }

        $body = $this->getJsonBody();
        [$fields, $error] = $this->validateFields($body);
        if ($error) {
            return $this->errorResponse($error, 400);
        }
        [$org, $event, $error] = $this->resolveTarget($body, $member);
        if ($error) {
            return $this->errorResponse($error, 400);
        }

        $feedback->update($fields);
        $feedback->OrganizationID = $org->ID;
        $feedback->EventID = $event ? $event->ID : 0;
        $feedback->write();

        return $this->successResponse(['feedback' => $this->formatDetail($feedback, $member)]);
    }

    /** DELETE /api/v1/orgfeedback/remove/{id} — Verfasser oder FEEDBACK_MODERATE */
    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $feedback = $this->findFeedback($request, $member);
        if (!$feedback) {
            return $this->errorResponse('Feedback nicht gefunden', 404);
        }
        if (!$feedback->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $feedback->delete();
        return $this->successResponse();
    }

    /**
     * POST /api/v1/orgfeedback/vote/{id} — schaltet das eigene "+1" um (ein erneuter
     * Klick nimmt es zurück). Mit {"Vote": true|false} lässt sich der Zustand auch setzen.
     */
    public function vote(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $feedback = $this->findFeedback($request, $member);
        if (!$feedback) {
            return $this->errorResponse('Feedback nicht gefunden', 404);
        }

        $existing = OrgFeedbackVote::get()->filter(['FeedbackID' => $feedback->ID, 'MemberID' => $member->ID])->first();
        $body = $this->getJsonBody();
        $want = array_key_exists('Vote', $body) ? (bool) $body['Vote'] : !$existing;

        if ($want && !$existing) {
            OrgFeedbackVote::create(['FeedbackID' => $feedback->ID, 'MemberID' => $member->ID])->write();
        } elseif (!$want && $existing) {
            $existing->delete();
        }

        return $this->successResponse([
            'VoteCount' => $feedback->Votes()->count(),
            'HasVoted'  => $want,
        ]);
    }

    /** POST /api/v1/orgfeedback/comment/{id} — Content (reiner Text) */
    public function comment(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $feedback = $this->findFeedback($request, $member);
        if (!$feedback) {
            return $this->errorResponse('Feedback nicht gefunden', 404);
        }

        $content = trim((string) ($this->getJsonBody()['Content'] ?? ''));
        if ($content === '') {
            return $this->errorResponse('Der Kommentar ist leer', 400);
        }
        if (mb_strlen($content) > self::MAX_COMMENT_LENGTH) {
            return $this->errorResponse('Der Kommentar ist zu lang', 400);
        }

        $comment = OrgFeedbackComment::create([
            'Content'    => $content,
            'FeedbackID' => $feedback->ID,
            'AuthorID'   => $member->ID,
        ]);
        $comment->write();

        PushNotificationService::notifyFeedbackComment($comment);

        return $this->successResponse(['comment' => $this->formatComment($comment, $member)]);
    }

    /** DELETE /api/v1/orgfeedback/commentRemove/{commentId} */
    public function commentRemove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $comment = OrgFeedbackComment::get()->byID((int) $request->param('ID'));
        $feedback = $comment ? $comment->Feedback() : null;
        if (!$feedback || !$feedback->exists() || !$feedback->isViewableBy($member)) {
            return $this->errorResponse('Kommentar nicht gefunden', 404);
        }
        if (!$comment->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $comment->delete();
        return $this->successResponse();
    }

    /**
     * POST /api/v1/orgfeedback/commentLike/{commentId} — schaltet den eigenen Daumen hoch um
     * (ein erneuter Klick nimmt ihn zurück). Mit {"Like": true|false} lässt sich der Zustand
     * auch setzen. Antwort: der Kommentar mit neuem LikeCount/HasLiked/Likers.
     */
    public function commentLike(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $comment = OrgFeedbackComment::get()->byID((int) $request->param('ID'));
        $feedback = $comment ? $comment->Feedback() : null;
        if (!$feedback || !$feedback->exists() || !$feedback->isViewableBy($member)) {
            return $this->errorResponse('Kommentar nicht gefunden', 404);
        }

        $existing = OrgFeedbackCommentLike::get()->filter(['CommentID' => $comment->ID, 'MemberID' => $member->ID])->first();
        $body = $this->getJsonBody();
        $want = array_key_exists('Like', $body) ? (bool) $body['Like'] : !$existing;

        if ($want && !$existing) {
            OrgFeedbackCommentLike::create(['CommentID' => $comment->ID, 'MemberID' => $member->ID])->write();
        } elseif (!$want && $existing) {
            $existing->delete();
        }

        $likes = $this->loadCommentLikes([$comment->ID]);
        return $this->successResponse(['comment' => $this->formatComment($comment, $member, $likes[$comment->ID] ?? [])]);
    }

    /**
     * GET /api/v1/orgfeedback/similar?title=…&organization=ID&event=ID&exclude=ID
     * Feedback mit ähnlichem Titel in derselben Organisation (mit Event: zuerst beim
     * selben Event, sonst in der ganzen Organisation).
     */
    public function similar(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $title = trim((string) $request->getVar('title'));
        $org = Organization::get()->byID((int) $request->getVar('organization'));
        if (mb_strlen($title) < 3 || !$org || !$member->isActiveMemberOfOrg($org)) {
            return $this->successResponse(['similar' => []]);
        }

        $candidates = OrgFeedback::get()->filter('OrganizationID', $org->ID);
        if ($exclude = (int) $request->getVar('exclude')) {
            $candidates = $candidates->exclude('ID', $exclude);
        }

        $matches = OrgFeedbackSimilarity::findSimilar($title, $candidates);
        // Gleiches Event zuerst
        $eventID = (int) $request->getVar('event');
        usort($matches, fn ($a, $b) =>
            [(int) ($b['feedback']->EventID === $eventID), $b['score']] <=> [(int) ($a['feedback']->EventID === $eventID), $a['score']]);

        $records = array_map(fn ($m) => $m['feedback'], $matches);
        $counts = $this->loadCounts(array_map(fn (OrgFeedback $f) => (int) $f->ID, $records), $member);

        return $this->successResponse([
            'similar' => array_map(fn (OrgFeedback $f) => $this->formatSummary($f, $counts), $records),
        ]);
    }

    /**
     * POST /api/v1/orgfeedback/createTask/{id} — macht aus dem Feedback eine Aufgabe
     * (TASKS_CREATE in der Organisation). Das Feedback bleibt bestehen und verweist darauf.
     */
    public function createTask(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $feedback = $this->findFeedback($request, $member);
        if (!$feedback) {
            return $this->errorResponse('Feedback nicht gefunden', 404);
        }
        if (!$this->tasksEnabled($feedback) || !$feedback->canConvertToTask($member)) {
            return $this->errorResponse('Keine Berechtigung, Aufgaben zu erstellen', 403);
        }
        $existing = $feedback->LinkedTaskID ? $feedback->LinkedTask() : null;
        if ($existing && $existing->exists()) {
            return $this->errorResponse('Aus diesem Feedback wurde schon eine Aufgabe erstellt', 400);
        }

        $body = $this->getJsonBody();
        $title = trim((string) ($body['Title'] ?? '')) ?: $feedback->Title;

        $author = $feedback->Author();
        $event = $feedback->getEventOrNull();
        $source = 'Aus Feedback von ' . ($author && $author->exists() ? $author->getDisplayName() : 'unbekannt')
            . ($event ? ' zum Event "' . $event->Title . '"' : '')
            . ' (' . $feedback->obj('Created')->Format('dd.MM.y') . ')';
        $text = OrgFeedback::plainText((string) $feedback->Content);

        $task = Task::create();
        $task->Title = mb_substr($title, 0, 255);
        $task->Description = trim($text . "\n\n" . $source);
        $task->OrganizationID = $feedback->OrganizationID;
        $task->OwnerID = $member->ID;
        $task->write();

        $feedback->LinkedTaskID = $task->ID;
        $feedback->write();

        return $this->successResponse([
            'task'     => ['ID' => $task->ID, 'Hash' => $task->Hash, 'Title' => $task->Title],
            'feedback' => $this->formatDetail($feedback, $member),
        ]);
    }

    /** GET /api/v1/orgfeedback/eventFeedback/{eventId} — Feedback-Karte auf der Event-Seite */
    public function eventFeedback(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $event = OrgEvent::get()->byID((int) $request->param('ID'));
        if (!$event || !$event->isInternalFor($member)) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }

        $org = $event->Organization();
        $enabled = $org && $org->exists() && (bool) $org->EnableFeedback;

        $data = [];
        if ($enabled) {
            $records = OrgFeedback::get()->filter('EventID', $event->ID)->toArray();
            $counts = $this->loadCounts(array_map(fn (OrgFeedback $f) => (int) $f->ID, $records), $member);
            $data = array_map(fn (OrgFeedback $f) => $this->formatSummary($f, $counts), $records);
            usort($data, fn ($a, $b) => [$b['VoteCount'], $b['Created']] <=> [$a['VoteCount'], $a['Created']]);
        }

        return $this->successResponse([
            'feedback'  => $data,
            'CanCreate' => $enabled,
        ]);
    }
}
