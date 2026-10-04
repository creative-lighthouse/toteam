<?php

namespace App\Controllers\Api;

use App\Announcements\FeedPost;
use App\Controllers\ApiController;
use App\Teams\OrgEvent;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\AnnouncementsApiController
 *
 * Feed des Mitteilungs-Totems (FeedPost) — ersetzt die früheren Mitteilungen.
 */
class AnnouncementsApiController extends ApiController
{
    private static $url_segment = 'api/v1/announcements';

    private static $allowed_actions = [
        'index',
        'feed',
        'post',
        'feedStore',
        'feedRemove',
        'mentionSearch',
    ];

    protected function getDefaultAction()
    {
        return 'feed';
    }

    /**
     * GET /api/v1/announcements/feed           → aktuelle Feed-Beiträge
     * GET /api/v1/announcements/feed?archive=1 → abgelaufene Feed-Beiträge (neueste Ablaufzeit zuerst)
     *
     * Die neuesten Feed-Beiträge, die man sehen darf:
     * öffentliche, interne der eigenen Organisationen und die eigenen. Dazu die Auswahl
     * fürs Eingabeformular (postAsOrganizations: darf im Namen posten, organizations:
     * alle eigenen Organisationen für interne Beiträge).
     */
    public function feed(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $data = [];
        foreach (FeedPost::visibleTo($member, (bool) $request->getVar('archive')) as $post) {
            $data[] = $post->toApi($member);
        }

        $organizations = [];
        foreach ($member->getOrganizationIDs() as $orgID) {
            $org = Organization::get()->byID($orgID);
            if ($org && $member->isActiveMemberOfOrg($org)) {
                $organizations[] = ['ID' => $org->ID, 'Title' => $org->Title, 'LogoURL' => $org->RenderLogo(40)];
            }
        }

        return $this->jsonResponse([
            'posts'               => $data,
            'organizations'       => $organizations,
            'postAsOrganizations' => $this->getCreateOrganizationsData($member),
        ]);
    }

    /** GET /api/v1/announcements — wie /feed */
    public function index(HTTPRequest $request): HTTPResponse
    {
        return $this->feed($request);
    }

    /** GET /api/v1/announcements/post/$ID — ein Beitrag (Detailseite, Links aus Push-Benachrichtigungen) */
    public function post(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $post = FeedPost::get()->byID((int) $request->param('ID'));
        if (!$post || !$post->isViewableBy($member)) {
            return $this->errorResponse('Beitrag nicht gefunden', 404);
        }
        return $this->jsonResponse(['post' => $post->toApi($member)]);
    }

    /**
     * POST /api/v1/announcements/feedStore
     * Body: { Content (HTML: p, br, strong, em, u), PostAs: 'person'|'organization', OrganizationID?, Visibility: 'Public'|'Internal', InternalOrganizationID?, ReleaseDate? ("YYYY-MM-DDTHH:MM", in der Zukunft = geplant), ExpiryDate? (ab dann nur noch im Archiv), EventID? (Event teilen — Text dann optional) }
     * Als Organisation (braucht ANNOUNCEMENTS_CREATE) ist ein interner Beitrag immer für diese Organisation.
     */
    public function feedStore(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = $this->getJsonBody();
        // HTML aus dem Editor (fett/kursiv/unterstrichen) — bereinigt, gezählt wird der reine Text
        $content = FeedPost::sanitizeContent((string) ($body['Content'] ?? ''));
        $text = FeedPost::plainText($content);

        // Geteiltes Event: muss für einen selbst sichtbar sein
        $event = null;
        if (!empty($body['EventID'])) {
            $event = OrgEvent::get()->byID((int) $body['EventID']);
            if (!$event || !$event->isViewableBy($member)) {
                return $this->errorResponse('Event nicht gefunden', 404);
            }
        }
        if ($text === '' && !$event) {
            return $this->errorResponse('Bitte einen Text eingeben', 400);
        }
        if ($text === '') {
            $content = '';
        }
        if (mb_strlen($text) > FeedPost::MAX_LENGTH) {
            return $this->errorResponse('Der Beitrag darf höchstens ' . FeedPost::MAX_LENGTH . ' Zeichen lang sein', 400);
        }

        $visibility = ($body['Visibility'] ?? '') === FeedPost::VISIBILITY_INTERNAL
            ? FeedPost::VISIBILITY_INTERNAL
            : FeedPost::VISIBILITY_PUBLIC;

        $postAsOrg = null;
        if (($body['PostAs'] ?? 'person') === 'organization') {
            $postAsOrg = Organization::get()->byID((int) ($body['OrganizationID'] ?? 0));
            if (!$postAsOrg || !$member->hasOrgPermission($postAsOrg, OrgPermissions::ANNOUNCEMENTS_CREATE)) {
                return $this->errorResponse('Keine Berechtigung, im Namen dieser Organisation zu posten', 403);
            }
        }

        $internalOrg = null;
        if ($visibility === FeedPost::VISIBILITY_INTERNAL) {
            $internalOrg = $postAsOrg ?? Organization::get()->byID((int) ($body['InternalOrganizationID'] ?? 0));
            if (!$internalOrg || !$member->isActiveMemberOfOrg($internalOrg)) {
                return $this->errorResponse('Bitte eine deiner Organisationen für den internen Beitrag wählen', 400);
            }
        }

        // Interne Events nur intern für ihre Organisation teilen — sonst sähen Außenstehende
        // einen Beitrag ohne Event (oder schlimmer: das Event)
        if ($event && !$event->IsPublic) {
            if ($visibility !== FeedPost::VISIBILITY_INTERNAL || (int) $internalOrg->ID !== (int) $event->OrganizationID) {
                return $this->errorResponse('Dieses Event ist intern und lässt sich nur intern für ' . $event->Organization()->Title . ' teilen', 400);
            }
        }

        $releaseDate = $this->parseDateTime($body['ReleaseDate'] ?? null);
        if ($releaseDate === false) {
            return $this->errorResponse('Ungültiger Zeitpunkt', 400);
        }
        // Ein Zeitpunkt in der Vergangenheit heißt einfach: sofort
        if ($releaseDate && strtotime($releaseDate) <= time()) {
            $releaseDate = null;
        }
        $expiryDate = $this->parseDateTime($body['ExpiryDate'] ?? null);
        if ($expiryDate === false) {
            return $this->errorResponse('Ungültiger Zeitpunkt', 400);
        }
        if ($expiryDate && strtotime($expiryDate) <= ($releaseDate ? strtotime($releaseDate) : time())) {
            return $this->errorResponse('Das Ablaufdatum muss nach der Veröffentlichung liegen', 400);
        }

        $post = FeedPost::create();
        $post->Content = $content;
        $post->ReleaseDate = $releaseDate;
        $post->ExpiryDate = $expiryDate;
        $post->EventID = $event ? $event->ID : 0;
        $post->Visibility = $visibility;
        $post->AuthorID = $member->ID;
        $post->OrganizationID = $postAsOrg ? $postAsOrg->ID : 0;
        $post->InternalOrganizationID = $internalOrg ? $internalOrg->ID : 0;
        $post->write();

        return $this->successResponse(['post' => $post->toApi($member)], $releaseDate ? 'Beitrag geplant' : 'Beitrag veröffentlicht');
    }

    /** DELETE /api/v1/announcements/feedRemove/$ID */
    public function feedRemove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $post = FeedPost::get()->byID((int) $request->param('ID'));
        if (!$post || !$post->isViewableBy($member)) {
            return $this->errorResponse('Beitrag nicht gefunden', 404);
        }
        if (!$post->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }
        $post->delete();
        return $this->successResponse([], 'Beitrag gelöscht');
    }

    /**
     * GET /api/v1/announcements/mentionSearch?q=… — Personen mit Benutzername für @-Markierungen,
     * passend zum Anfang von Benutzername, Vor- oder Nachname (höchstens 8)
     */
    public function mentionSearch(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $q = trim((string) $request->getVar('q'));
        if ($q === '') {
            return $this->jsonResponse(['members' => []]);
        }
        $matches = Member::get()
            ->exclude('Username', [null, ''])
            ->filterAny([
                'Username:StartsWith'  => $q,
                'FirstName:StartsWith' => $q,
                'Surname:StartsWith'   => $q,
            ])
            ->sort('Username', 'ASC')
            ->limit(8);

        $data = [];
        foreach ($matches as $m) {
            $data[] = [
                'ID'       => $m->ID,
                'Name'     => $m->getDisplayName(),
                'Username' => $m->Username,
                'Avatar'   => $m->RenderProfileImage(),
            ];
        }
        return $this->jsonResponse(['members' => $data]);
    }

    /**
     * Organisationen, in deren Namen der Nutzer posten darf (ANNOUNCEMENTS_CREATE).
     */
    private function getCreateOrganizationsData(Member $member): array
    {
        $data = [];
        foreach ($member->getOrganizationIDs() as $orgID) {
            $org = Organization::get()->byID($orgID);
            if ($org && $member->hasOrgPermission($org, OrgPermissions::ANNOUNCEMENTS_CREATE)) {
                $data[] = ['ID' => $org->ID, 'Title' => $org->Title, 'LogoURL' => $org->RenderLogo(40)];
            }
        }
        return $data;
    }

    /**
     * Wandelt einen Wert aus einem datetime-local-Feld ("YYYY-MM-DDTHH:MM") in das DB-Format um.
     * Liefert null bei leerem Wert und false bei ungültigem Wert.
     */
    private function parseDateTime(?string $value): string|null|false
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? false : date('Y-m-d H:i:s', $timestamp);
    }
}
