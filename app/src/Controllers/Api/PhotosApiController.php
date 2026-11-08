<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Photos\BunnyStorage;
use App\Photos\Photo;
use App\Photos\PhotoStorageConfig;
use App\Photos\PhotoTag;
use App\Skript\Script;
use App\Skript\ScriptRole;
use App\Teams\Organization;
use App\Teams\OrganizationMembership;
use App\Teams\OrgEvent;
use App\Teams\OrgPermissions;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;

/**
 * Foto-Totem: Bilder einer Organisation, gespeichert im Bunny-Account der Organisation
 * ({@see PhotoStorageConfig}), getaggt nach Personen, Charakteren (Skript-Rollen),
 * Schlagworten und Events.
 */
class PhotosApiController extends ApiController
{
    private static $url_segment = 'api/v1/photos';

    private static $allowed_actions = [
        'index',
        'options',
        'detail',
        'upload',
        'update',
        'remove',
        'untagMe',
        'settings',
        'testSettings',
        'eventPhotos',
        'download',
        'organizations',
        'folders',
    ];

    private const PAGE_SIZE = 60;
    private const MAX_FULL_SIZE = 20 * 1024 * 1024;
    private const MAX_THUMB_SIZE = 2 * 1024 * 1024;

    /** @var array<int, ?BunnyStorage> */
    private array $storages = [];

    protected function getDefaultAction()
    {
        return 'index';
    }

    // ── Hilfen ────────────────────────────────────────────────────────────

    /** Organisationen des Nutzers mit aktivem Foto-Totem */
    private function photoOrgs(Member $member): array
    {
        $ids = $member->getOrganizationIDs();
        if (!$ids) {
            return [];
        }
        return Organization::get()->filter(['ID' => $ids, 'EnablePhotos' => true])->sort('Title')->toArray();
    }

    private function storageFor(int $orgID): ?BunnyStorage
    {
        if (!array_key_exists($orgID, $this->storages)) {
            $config = PhotoStorageConfig::forOrganization($orgID);
            $this->storages[$orgID] = $config && $config->isComplete() ? $config->client() : null;
        }
        return $this->storages[$orgID];
    }

    private function findPhoto(HTTPRequest $request, Member $member): ?Photo
    {
        $photo = Photo::get()->byID((int) $request->param('ID'));
        return $photo && $photo->isViewableBy($member) ? $photo : null;
    }

    /** Organisation per ID, in der der Nutzer Mitglied ist und das Totem aktiv ist */
    private function findOrg(int $orgID, Member $member): ?Organization
    {
        $org = $orgID ? Organization::get()->byID($orgID) : null;
        return $org && $org->EnablePhotos && $member->isActiveMemberOfOrg($org) ? $org : null;
    }

    private function formatMember(?Member $member): ?array
    {
        if (!$member || !$member->exists()) {
            return null;
        }
        return [
            'ID'      => $member->ID,
            'Name'    => $member->getDisplayName(),
            'Avatar'  => $member->RenderProfileImage(),
            'IsGuest' => (bool) $member->IsGuest,
        ];
    }

    private function formatSummary(Photo $photo): array
    {
        $storage = $this->storageFor((int) $photo->OrganizationID);
        return [
            'ID'             => $photo->ID,
            'OrganizationID' => (int) $photo->OrganizationID,
            'Title'          => $photo->Title ?: null,
            'OriginalName'   => $photo->OriginalName ?: null,
            'Copyright'      => $photo->Copyright ?: null,
            'Width'          => (int) $photo->Width,
            'Height'         => (int) $photo->Height,
            'TakenAt'        => $photo->TakenAt ?: null,
            'Created'        => $photo->Created,
            'ThumbURL'       => $storage && $photo->ThumbPath ? $storage->publicURL($photo->ThumbPath) : null,
            'URL'            => $storage && $photo->StoragePath ? $storage->publicURL($photo->StoragePath) : null,
        ];
    }

    /**
     * Für die Listenansicht: Namen der Personen, Charaktere und Schlagworte je Foto —
     * mit drei Abfragen für die ganze Seite statt drei pro Foto.
     * @param array[] $summaries formatSummary()-Form
     */
    private function withTagSummaries(array $summaries): array
    {
        $ids = array_map(fn (array $s) => (int) $s['ID'], $summaries);
        if (!$ids) {
            return $summaries;
        }
        $in = implode(',', $ids);

        $people = [];
        $memberIDs = [];
        foreach (DB::query('SELECT "PhotoID", "MemberID" FROM "Photo_People" WHERE "PhotoID" IN (' . $in . ')') as $row) {
            $people[(int) $row['PhotoID']][] = (int) $row['MemberID'];
            $memberIDs[(int) $row['MemberID']] = true;
        }
        $names = [];
        if ($memberIDs) {
            foreach (Member::get()->filter('ID', array_keys($memberIDs)) as $person) {
                $names[(int) $person->ID] = $person->getDisplayName();
            }
        }

        $characters = [];
        foreach (DB::query(
            'SELECT "Photo_Characters"."PhotoID", "ScriptRole"."Title" FROM "Photo_Characters"'
            . ' INNER JOIN "ScriptRole" ON "ScriptRole"."ID" = "Photo_Characters"."ScriptRoleID"'
            . ' WHERE "Photo_Characters"."PhotoID" IN (' . $in . ') ORDER BY "ScriptRole"."Title"'
        ) as $row) {
            $characters[(int) $row['PhotoID']][] = $row['Title'];
        }

        $tags = [];
        foreach (DB::query(
            'SELECT "Photo_Tags"."PhotoID", "PhotoTag"."Title" FROM "Photo_Tags"'
            . ' INNER JOIN "PhotoTag" ON "PhotoTag"."ID" = "Photo_Tags"."PhotoTagID"'
            . ' WHERE "Photo_Tags"."PhotoID" IN (' . $in . ') ORDER BY "PhotoTag"."Title"'
        ) as $row) {
            $tags[(int) $row['PhotoID']][] = $row['Title'];
        }

        return array_map(function (array $s) use ($people, $names, $characters, $tags) {
            $id = (int) $s['ID'];
            $s['PeopleNames'] = array_values(array_filter(array_map(fn (int $m) => $names[$m] ?? null, $people[$id] ?? [])));
            $s['CharacterNames'] = $characters[$id] ?? [];
            $s['TagNames'] = $tags[$id] ?? [];
            return $s;
        }, $summaries);
    }

    /**
     * Präfixe der Suche ("charakter:Andrea") → Art des Tags. Mit Präfix wird nur in dieser
     * Art und nur nach genau diesem Namen gesucht (Groß-/Kleinschreibung egal).
     */
    private const SEARCH_PREFIXES = [
        'person'     => 'people',
        'personen'   => 'people',
        'character'  => 'characters',
        'charakter'  => 'characters',
        'charaktere' => 'characters',
        'rolle'      => 'characters',
        'tag'        => 'tags',
        'schlagwort' => 'tags',
        'titel'      => 'text',
        'text'       => 'text',
        'copyright'  => 'text',
    ];

    /**
     * Suchtext in Begriffe zerlegen: Wörter, "Wörter in Anführungszeichen" und
     * präfix:wert bzw. präfix:"Wert mit Leerzeichen".
     * @return array<array{kind: ?string, value: string}> kind null = überall suchen
     */
    public static function parseSearch(string $search): array
    {
        preg_match_all('/(?:(\p{L}+):)?(?:"([^"]*)"?|(\S+))/u', $search, $matches, PREG_SET_ORDER);
        $terms = [];
        foreach ($matches as $m) {
            $prefix = mb_strtolower($m[1] ?? '');
            $value = trim(mb_strtolower(($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')));
            $kind = $prefix !== '' ? (self::SEARCH_PREFIXES[$prefix] ?? null) : null;
            if ($prefix !== '' && $kind === null) {
                // Unbekanntes Präfix (z.B. "12:30"): als normales Wort behandeln
                $value = mb_strtolower($m[0]);
            }
            if ($value !== '') {
                $terms[] = ['kind' => $kind, 'value' => $value];
            }
        }
        return array_slice($terms, 0, 8);
    }

    /**
     * Freie Suche über Titel, Beschreibung, Personen, Charaktere und Schlagworte. Alle
     * Begriffe müssen passen. Ohne Präfix genügt ein Teil des Namens irgendwo, mit Präfix
     * ({@see SEARCH_PREFIXES}) muss der Name dieser Art genau stimmen — bei Personen der
     * angezeigte Name oder der Vorname. Personen werden über den angezeigten Namen gesucht,
     * damit ausgeblendete Nachnamen nicht über die Suche auffindbar sind.
     * @param int[] $orgIDs
     * @return int[] passende Foto-IDs
     */
    private function searchPhotoIDs(string $search, array $orgIDs): array
    {
        $terms = self::parseSearch($search);
        if (!$orgIDs || !$terms) {
            return [];
        }
        $orgIn = implode(',', array_map('intval', $orgIDs));

        // Personen der Organisationen mit angezeigtem Namen und Vornamen
        $memberIDs = OrganizationMembership::get()->filter(['OrganizationID' => $orgIDs, 'Role' => 'member'])->column('MemberID');
        $members = [];
        foreach (Member::get()->filter('ID', $memberIDs ?: [0]) as $person) {
            $members[(int) $person->ID] = [
                'name'  => mb_strtolower($person->getDisplayName()),
                'first' => mb_strtolower((string) $person->FirstName),
            ];
        }

        $result = null;
        foreach ($terms as ['kind' => $kind, 'value' => $value]) {
            $exact = $kind !== null;
            $operator = $exact ? '=' : 'LIKE';
            $param = $exact ? $value : '%' . addcslashes($value, '%_\\') . '%';
            $ids = [];

            if ($kind === null || $kind === 'text') {
                $ids = array_merge($ids, DB::prepared_query(
                    'SELECT "ID" FROM "Photo" WHERE "OrganizationID" IN (' . $orgIn . ')'
                    . ' AND (LOWER("Title") LIKE ? OR LOWER("Description") LIKE ? OR LOWER("Copyright") LIKE ?)',
                    array_fill(0, 3, '%' . addcslashes($value, '%_\\') . '%')
                )->column());
            }
            if ($kind === null || $kind === 'characters') {
                $ids = array_merge($ids, DB::prepared_query(
                    'SELECT "Photo_Characters"."PhotoID" FROM "Photo_Characters"'
                    . ' INNER JOIN "ScriptRole" ON "ScriptRole"."ID" = "Photo_Characters"."ScriptRoleID"'
                    . ' WHERE LOWER("ScriptRole"."Title") ' . $operator . ' ?',
                    [$param]
                )->column());
            }
            if ($kind === null || $kind === 'tags') {
                $ids = array_merge($ids, DB::prepared_query(
                    'SELECT "Photo_Tags"."PhotoID" FROM "Photo_Tags"'
                    . ' INNER JOIN "PhotoTag" ON "PhotoTag"."ID" = "Photo_Tags"."PhotoTagID"'
                    . ' WHERE "PhotoTag"."OrganizationID" IN (' . $orgIn . ') AND LOWER("PhotoTag"."Title") ' . $operator . ' ?',
                    [$param]
                )->column());
            }
            if ($kind === null || $kind === 'people') {
                $matching = array_keys(array_filter($members, fn (array $m) => $exact
                    ? ($m['name'] === $value || $m['first'] === $value)
                    : str_contains($m['name'], $value)));
                if ($matching) {
                    $ids = array_merge($ids, DB::query(
                        'SELECT "PhotoID" FROM "Photo_People" WHERE "MemberID" IN (' . implode(',', $matching) . ')'
                    )->column());
                }
            }

            $ids = array_unique(array_map('intval', $ids));
            $result = $result === null ? $ids : array_values(array_intersect($result, $ids));
            if (!$result) {
                return [];
            }
        }
        return $result ?? [];
    }

    private function formatDetail(Photo $photo, Member $member): array
    {
        $canEdit = $photo->isEditableBy($member);
        $events = [];
        foreach ($photo->Events()->sort('DateStart DESC') as $event) {
            $events[] = ['ID' => $event->ID, 'Title' => $event->Title, 'URLSegment' => $event->URLSegment];
        }
        $people = [];
        foreach ($photo->People() as $person) {
            $people[] = $this->formatMember($person);
        }
        $characters = [];
        foreach ($photo->Characters() as $role) {
            $characters[] = $this->formatCharacter($role);
        }
        $tags = [];
        foreach ($photo->Tags() as $tag) {
            $tags[] = ['ID' => $tag->ID, 'Title' => $tag->Title];
        }

        return array_merge($this->formatSummary($photo), [
            'Description'  => (string) $photo->Description,
            'OriginalName' => $photo->OriginalName ?: null,
            'FileSize'     => (int) $photo->FileSize,
            'Latitude'     => $photo->hasCoordinates() ? (float) $photo->Latitude : null,
            'Longitude'    => $photo->hasCoordinates() ? (float) $photo->Longitude : null,
            'UploadedBy'   => $this->formatMember($photo->UploadedBy()),
            'Events'       => $events,
            'People'       => $people,
            'Characters'   => $characters,
            'Tags'         => $tags,
            'CanEdit'      => $canEdit,
            // Je Art einzeln, damit sie sich später getrennt vergeben lassen (derzeit alle = CanEdit)
            'CanTag'       => [
                'People'     => $canEdit,
                'Characters' => $canEdit,
                'Tags'       => $canEdit,
                'Events'     => $canEdit,
            ],
            'CanDelete'    => $photo->isDeletableBy($member),
            'IsTagged'     => $photo->People()->filter('ID', $member->ID)->exists(),
        ]);
    }

    private function formatCharacter(ScriptRole $role): array
    {
        $script = $role->Script();
        return [
            'ID'     => $role->ID,
            'Title'  => $role->Title,
            'Script' => $script && $script->exists() ? $script->Title : null,
        ];
    }

    /**
     * Personen der Organisation (Mitglieder inkl. Gäste) — die, die getaggt werden dürfen.
     * @return int[]
     */
    private function orgMemberIDs(int $orgID): array
    {
        return array_map('intval', OrganizationMembership::get()
            ->filter(['OrganizationID' => $orgID, 'Role' => 'member'])
            ->column('MemberID'));
    }

    /** @return int[] Skript-Rollen aus Skripten der Organisation */
    private function orgCharacterIDs(int $orgID): array
    {
        $scriptIDs = Script::get()->filter('OrganizationID', $orgID)->column('ID');
        return $scriptIDs ? array_map('intval', ScriptRole::get()->filter('ScriptID', $scriptIDs)->column('ID')) : [];
    }

    /** IDs aus der Eingabe auf die erlaubten beschränken */
    private static function allowedIDs(mixed $input, array $allowed): array
    {
        $ids = array_map('intval', is_array($input) ? $input : []);
        return array_values(array_intersect(array_unique($ids), $allowed));
    }

    // ── Liste & Auswahl ───────────────────────────────────────────────────

    /**
     * Organisationen für das Totem: nur die mit eingerichtetem Speicher — oder solche, in
     * denen der Nutzer ihn einrichten darf (dort erscheint der Einrichten-Hinweis)
     * @return array[] formatOrg()-Form
     */
    private function visibleOrgs(Member $member): array
    {
        $orgs = $this->photoOrgs($member);
        $ids = array_map(fn (Organization $o) => (int) $o->ID, $orgs);
        $counts = $ids ? $this->countBy(
            'SELECT "OrganizationID" AS "Key", COUNT(*) AS "Count" FROM "Photo" WHERE "OrganizationID" IN (' . implode(',', $ids) . ') GROUP BY "OrganizationID"'
        ) : [];
        return array_values(array_filter(
            array_map(fn (Organization $o) => $this->formatOrg($o, $member, $counts[(int) $o->ID] ?? 0), $orgs),
            fn (array $o) => $o['Configured'] || $o['CanManageStorage']
        ));
    }

    /** @return array<int,int> Ergebnis einer "Key"/"Count"-Abfrage als [Key => Count] */
    private function countBy(string $sql): array
    {
        $counts = [];
        foreach (DB::query($sql) as $row) {
            $counts[(int) $row['Key']] = (int) $row['Count'];
        }
        return $counts;
    }

    /** IDs der Fotos, die mindestens einem Event zugeordnet sind */
    private function photoIDsWithEvent(int $orgID): array
    {
        return DB::prepared_query(
            'SELECT DISTINCT "Photo_Events"."PhotoID" FROM "Photo_Events"'
            . ' INNER JOIN "Photo" ON "Photo"."ID" = "Photo_Events"."PhotoID" WHERE "Photo"."OrganizationID" = ?',
            [$orgID]
        )->column();
    }

    /**
     * GET /api/v1/photos?organization=ID&event=ID|none&person=ID&character=ID&tag=ID&offset=N
     * Fotos (neueste Aufnahme zuerst) seitenweise plus die Organisationen mit Rechten.
     * `event=none` = Fotos ohne Event (nur zusammen mit `organization`).
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgs = $this->visibleOrgs($member);
        $orgIDs = array_column($orgs, 'ID');

        $list = Photo::get()->filter('OrganizationID', $orgIDs ?: [0]);
        $orgID = (int) $request->getVar('organization');
        if ($orgID) {
            $list = $list->filter('OrganizationID', $orgID);
        }
        $eventFilter = $request->getVar('event');
        if ($eventFilter === 'none' && $orgID) {
            if ($withEvent = $this->photoIDsWithEvent($orgID)) {
                $list = $list->exclude('ID', $withEvent);
            }
        } elseif ($eventID = (int) $eventFilter) {
            $list = $list->filter('Events.ID', $eventID);
        }
        if ($personID = (int) $request->getVar('person')) {
            $list = $list->filter('People.ID', $personID);
        }
        if ($characterID = (int) $request->getVar('character')) {
            $list = $list->filter('Characters.ID', $characterID);
        }
        if ($tagID = (int) $request->getVar('tag')) {
            $list = $list->filter('Tags.ID', $tagID);
        }
        if ($request->getVar('mine')) {
            $list = $list->filter('People.ID', $member->ID);
        }
        $search = trim((string) $request->getVar('q'));
        if ($search !== '') {
            $list = $list->filter('ID', $this->searchPhotoIDs($search, $orgID ? [$orgID] : $orgIDs) ?: [0]);
        }

        $total = $list->count();
        $offset = max(0, (int) $request->getVar('offset'));
        $photos = $this->withTagSummaries(array_map(
            fn (Photo $p) => $this->formatSummary($p),
            $list->limit(self::PAGE_SIZE, $offset)->toArray()
        ));

        return $this->jsonResponse([
            'photos'        => $photos,
            'total'         => $total,
            'hasMore'       => $offset + count($photos) < $total,
            'organizations' => $orgs,
        ]);
    }

    private function formatOrg(Organization $org, Member $member, int $photoCount = 0): array
    {
        $config = PhotoStorageConfig::forOrganization((int) $org->ID);
        return [
            'ID'               => $org->ID,
            'Title'            => $org->Title,
            'LogoURL'          => $org->RenderLogo(96),
            // Akzentfarbe aus dem Logo (null = ohne Logo bzw. farblos, siehe utils/orgColor.js)
            'Color'            => $org->getLogoColor(),
            'PhotoCount'       => $photoCount,
            'Configured'       => $config && $config->isComplete(),
            'CanUpload'        => $member->hasOrgPermission($org, OrgPermissions::PHOTOS_UPLOAD),
            'CanManage'        => $member->hasOrgPermission($org, OrgPermissions::PHOTOS_MANAGE),
            'CanManageStorage' => $member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_SETTINGS),
        ];
    }

    /** GET /api/v1/photos/organizations — die Organisationen (oberste Ordner-Ebene) */
    public function organizations(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        return $this->successResponse(['organizations' => $this->visibleOrgs($member)]);
    }

    /**
     * GET /api/v1/photos/folders/{orgID} — Ordner einer Organisation: jedes Event (neueste
     * zuerst, auch ohne Fotos — dort kann man hineinladen) und "Ohne Event", je mit Anzahl
     */
    public function folders(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $orgID = (int) $request->param('ID');
        $org = null;
        foreach ($this->visibleOrgs($member) as $candidate) {
            if ((int) $candidate['ID'] === $orgID) {
                $org = $candidate;
            }
        }
        if (!$org) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        $counts = $this->countBy(sprintf(
            'SELECT "Photo_Events"."OrgEventID" AS "Key", COUNT(*) AS "Count" FROM "Photo_Events"'
            . ' INNER JOIN "Photo" ON "Photo"."ID" = "Photo_Events"."PhotoID"'
            . ' WHERE "Photo"."OrganizationID" = %d GROUP BY "Photo_Events"."OrgEventID"',
            $orgID
        ));

        $events = [];
        foreach (OrgEvent::get()->filter('OrganizationID', $orgID)->sort(['DateStart' => 'DESC', 'Title' => 'ASC']) as $event) {
            $events[] = [
                'ID'         => $event->ID,
                'Title'      => $event->Title,
                'DateStart'  => $event->DateStart ?: null,
                'DateEnd'    => $event->DateEnd ?: null,
                'PhotoCount' => $counts[(int) $event->ID] ?? 0,
            ];
        }

        $total = Photo::get()->filter('OrganizationID', $orgID)->count();

        return $this->successResponse([
            'organization'    => $org,
            'events'          => $events,
            'withoutEvent'    => $total - count($this->photoIDsWithEvent($orgID)),
        ]);
    }

    /**
     * GET /api/v1/photos/options/{orgID} — Auswahl zum Filtern und Taggen: Personen,
     * Charaktere, Events und Schlagworte der Organisation
     */
    public function options(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $org = $this->findOrg((int) $request->param('ID'), $member);
        if (!$org) {
            return $this->errorResponse('Organisation nicht gefunden', 404);
        }

        $people = [];
        $memberIDs = $this->orgMemberIDs((int) $org->ID);
        foreach (Member::get()->filter('ID', $memberIDs ?: [0])->sort(['FirstName' => 'ASC', 'Surname' => 'ASC']) as $person) {
            $people[] = $this->formatMember($person);
        }

        $characters = [];
        $characterIDs = $this->orgCharacterIDs((int) $org->ID);
        foreach (ScriptRole::get()->filter('ID', $characterIDs ?: [0])->sort('Title') as $role) {
            $characters[] = $this->formatCharacter($role);
        }

        $events = [];
        foreach (OrgEvent::get()->filter('OrganizationID', $org->ID)->sort(['DateStart' => 'DESC', 'Title' => 'ASC']) as $event) {
            $events[] = ['ID' => $event->ID, 'Title' => $event->Title, 'DateStart' => $event->DateStart ?: null];
        }

        $tags = [];
        foreach (PhotoTag::get()->filter('OrganizationID', $org->ID) as $tag) {
            $tags[] = ['ID' => $tag->ID, 'Title' => $tag->Title];
        }

        // Bisher verwendete Copyright-Einträge als Vorschläge, die häufigsten zuerst
        $copyrights = DB::prepared_query(
            'SELECT "Copyright" FROM "Photo" WHERE "OrganizationID" = ? AND "Copyright" IS NOT NULL AND "Copyright" <> \'\''
            . ' GROUP BY "Copyright" ORDER BY COUNT(*) DESC, MAX("Created") DESC LIMIT 50',
            [$org->ID]
        )->column();

        return $this->successResponse([
            'people'     => $people,
            'characters' => $characters,
            'events'     => $events,
            'tags'       => $tags,
            'copyrights' => $copyrights,
        ]);
    }

    /** GET /api/v1/photos/detail/{id} */
    public function detail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $photo = $this->findPhoto($request, $member);
        if (!$photo) {
            return $this->errorResponse('Foto nicht gefunden', 404);
        }
        return $this->successResponse(['photo' => $this->formatDetail($photo, $member)]);
    }

    // ── Hochladen ─────────────────────────────────────────────────────────

    /**
     * POST /api/v1/photos/upload (multipart) — ein Foto: Dateien `full` und `thumb` (JPEG,
     * im Browser verkleinert), dazu OrganizationID, optional EventID, Tags (JSON-Liste),
     * OriginalName, TakenAt (ISO) sowie Latitude/Longitude aus den EXIF-Daten.
     */
    public function upload(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $org = $this->findOrg((int) $request->postVar('OrganizationID'), $member);
        if (!$org) {
            return $this->errorResponse('Bitte eine Organisation wählen, in der du Mitglied bist', 400);
        }
        if (!$member->hasOrgPermission($org, OrgPermissions::PHOTOS_UPLOAD)) {
            return $this->errorResponse('Du darfst in dieser Organisation keine Fotos hochladen', 403);
        }
        $storage = $this->storageFor((int) $org->ID);
        if (!$storage) {
            return $this->errorResponse('Für diese Organisation ist noch kein Foto-Speicher eingerichtet', 400);
        }

        $event = null;
        if ($eventID = (int) $request->postVar('EventID')) {
            $event = OrgEvent::get()->byID($eventID);
            if (!$event || (int) $event->OrganizationID !== (int) $org->ID) {
                return $this->errorResponse('Event nicht gefunden', 400);
            }
        }

        [$full, $error] = $this->checkUpload('full', self::MAX_FULL_SIZE);
        if ($error) {
            return $this->errorResponse($error, 400);
        }
        [$thumb, $error] = $this->checkUpload('thumb', self::MAX_THUMB_SIZE);
        if ($error) {
            return $this->errorResponse($error, 400);
        }

        $hash = sha1_file($full['tmp_name']);
        $existing = Photo::get()->filter(['OrganizationID' => $org->ID, 'Hash' => $hash])->first();
        if ($existing) {
            if ($event) {
                $existing->Events()->add($event);
            }
            // Fehlende Aufnahmedaten nachtragen (z.B. Foto erneut hochgeladen, nachdem es
            // geotaggt wurde oder ein früherer Upload sie nicht auslesen konnte)
            [$lat, $lng] = $this->parseCoordinates($request->postVar('Latitude'), $request->postVar('Longitude'));
            if ($lat !== null && !$existing->hasCoordinates()) {
                $existing->Latitude = $lat;
                $existing->Longitude = $lng;
            }
            if (!$existing->TakenAt) {
                $existing->TakenAt = $this->parseTakenAt((string) $request->postVar('TakenAt'));
            }
            if ($existing->isChanged()) {
                $existing->write();
            }
            return $this->successResponse([
                'photo'     => $this->formatSummary($existing),
                'duplicate' => true,
            ]);
        }

        $size = getimagesize($full['tmp_name']);
        $base = sprintf('photos/%d/%s/%s', $org->ID, date('Y-m'), bin2hex(random_bytes(16)));
        $photo = Photo::create([
            'OrganizationID' => $org->ID,
            'UploadedByID'   => $member->ID,
            'StoragePath'    => $base . '.jpg',
            'ThumbPath'      => $base . '_thumb.jpg',
            'OriginalName'   => mb_substr(trim((string) $request->postVar('OriginalName')), 0, 255) ?: null,
            'Copyright'      => self::cleanCopyright((string) $request->postVar('Copyright')),
            'Hash'           => $hash,
            'Width'          => (int) ($size[0] ?? 0),
            'Height'         => (int) ($size[1] ?? 0),
            'FileSize'       => (int) $full['size'],
            'ThumbSize'      => (int) $thumb['size'],
            'TakenAt'        => $this->parseTakenAt((string) $request->postVar('TakenAt')),
        ]);
        [$lat, $lng] = $this->parseCoordinates($request->postVar('Latitude'), $request->postVar('Longitude'));
        $photo->Latitude = $lat;
        $photo->Longitude = $lng;

        try {
            $storage->upload($full['tmp_name'], $photo->StoragePath);
            $storage->upload($thumb['tmp_name'], $photo->ThumbPath);
        } catch (\RuntimeException $e) {
            // Halb hochgeladenes wieder wegräumen
            try {
                $storage->delete($photo->StoragePath);
            } catch (\RuntimeException) {
            }
            return $this->errorResponse($e->getMessage(), 502);
        }

        $photo->write();
        if ($event) {
            $photo->Events()->add($event);
        }
        $tags = json_decode((string) $request->postVar('Tags'), true);
        foreach (is_array($tags) ? $tags : [] as $title) {
            if (is_string($title) && ($tag = PhotoTag::findOrCreate((int) $org->ID, $title))) {
                $photo->Tags()->add($tag);
            }
        }

        return $this->successResponse(['photo' => $this->formatSummary($photo), 'duplicate' => false]);
    }

    /** Copyright ohne vorangestelltes "©"/"(c)" speichern — die Anzeige setzt das Zeichen selbst davor */
    private static function cleanCopyright(string $value): ?string
    {
        $value = preg_replace('/^\s*(©|\(c\))\s*/iu', '', $value) ?? $value;
        return mb_substr(trim($value), 0, 255) ?: null;
    }

    /** @return array{0: ?array, 1: ?string} [Datei aus $_FILES, Fehler] */
    private function checkUpload(string $field, int $maxSize): array
    {
        $file = $_FILES[$field] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return [null, 'Datei fehlt oder ist zu groß'];
        }
        if ($file['size'] > $maxSize) {
            return [null, sprintf('Die Datei darf höchstens %d MB groß sein', $maxSize / 1024 / 1024)];
        }
        if (mime_content_type($file['tmp_name']) !== 'image/jpeg') {
            return [null, 'Nur JPEG wird akzeptiert'];
        }
        return [$file, null];
    }

    private function parseTakenAt(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $time = strtotime($value);
        // Kameras ohne gestellte Uhr liefern gern 1970 oder Zukunftsdaten
        if (!$time || $time < strtotime('1990-01-01') || $time > time() + 86400) {
            return null;
        }
        return date('Y-m-d H:i:s', $time);
    }

    /** @return array{0: ?float, 1: ?float} */
    private function parseCoordinates(mixed $lat, mixed $lng): array
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return [null, null];
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return [null, null];
        }
        return [round($lat, 7), round($lng, 7)];
    }

    // ── Bearbeiten ────────────────────────────────────────────────────────

    /**
     * PUT /api/v1/photos/update/{id} — Title, Description, EventIDs, PeopleIDs,
     * CharacterIDs, Tags (Liste von Titeln; neue werden angelegt)
     */
    public function update(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $photo = $this->findPhoto($request, $member);
        if (!$photo) {
            return $this->errorResponse('Foto nicht gefunden', 404);
        }
        if (!$photo->isEditableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $body = $this->getJsonBody();
        $orgID = (int) $photo->OrganizationID;

        if (array_key_exists('Title', $body)) {
            $photo->Title = mb_substr(trim((string) $body['Title']), 0, 255) ?: null;
        }
        if (array_key_exists('Description', $body)) {
            $photo->Description = mb_substr(trim((string) $body['Description']), 0, 5000) ?: null;
        }
        if (array_key_exists('Copyright', $body)) {
            $photo->Copyright = self::cleanCopyright((string) $body['Copyright']);
        }
        $photo->write();

        if (array_key_exists('EventIDs', $body)) {
            $allowed = array_map('intval', OrgEvent::get()->filter('OrganizationID', $orgID)->column('ID'));
            $photo->Events()->setByIDList(self::allowedIDs($body['EventIDs'], $allowed));
        }
        if (array_key_exists('PeopleIDs', $body)) {
            $photo->People()->setByIDList(self::allowedIDs($body['PeopleIDs'], $this->orgMemberIDs($orgID)));
        }
        if (array_key_exists('CharacterIDs', $body)) {
            $photo->Characters()->setByIDList(self::allowedIDs($body['CharacterIDs'], $this->orgCharacterIDs($orgID)));
        }
        if (array_key_exists('Tags', $body)) {
            $tagIDs = [];
            foreach (is_array($body['Tags']) ? $body['Tags'] : [] as $title) {
                if (is_string($title) && ($tag = PhotoTag::findOrCreate($orgID, $title))) {
                    $tagIDs[] = (int) $tag->ID;
                }
            }
            $photo->Tags()->setByIDList(array_unique($tagIDs));
            PhotoTag::pruneUnused($orgID);
        }

        return $this->successResponse(['photo' => $this->formatDetail($photo, $member)]);
    }

    /** DELETE /api/v1/photos/remove/{id} — löscht auch die Dateien bei Bunny */
    public function remove(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'DELETE') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $photo = $this->findPhoto($request, $member);
        if (!$photo) {
            return $this->errorResponse('Foto nicht gefunden', 404);
        }
        if (!$photo->isDeletableBy($member)) {
            return $this->errorResponse('Keine Berechtigung', 403);
        }

        $orgID = (int) $photo->OrganizationID;
        $photo->delete();
        PhotoTag::pruneUnused($orgID);
        return $this->successResponse();
    }

    /** POST /api/v1/photos/untagMe/{id} — jede Person darf ihre eigene Markierung entfernen */
    public function untagMe(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        $photo = $this->findPhoto($request, $member);
        if (!$photo) {
            return $this->errorResponse('Foto nicht gefunden', 404);
        }
        $photo->People()->remove($member);
        return $this->successResponse(['photo' => $this->formatDetail($photo, $member)]);
    }

    /**
     * GET /api/v1/photos/download/{id} — das Foto als Datei-Download. Läuft über den Server,
     * weil Browser `<a download>` bei fremden Domains (Bunny) ignorieren und das Bild nur öffnen.
     */
    public function download(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        $photo = $this->findPhoto($request, $member);
        if (!$photo) {
            return $this->errorResponse('Foto nicht gefunden', 404);
        }
        $storage = $this->storageFor((int) $photo->OrganizationID);
        if (!$storage || !$photo->StoragePath) {
            return $this->errorResponse('Für dieses Foto ist kein Speicher eingerichtet', 400);
        }

        try {
            $content = $storage->download($photo->StoragePath);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 502);
        }

        $filename = self::downloadFilename($photo);
        $response = $this->getResponse();
        $response->setStatusCode(200);
        $response->addHeader('Content-Type', 'image/jpeg');
        $response->addHeader('Content-Length', (string) strlen($content));
        $response->addHeader(
            'Content-Disposition',
            sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', preg_replace('/[^A-Za-z0-9._-]/', '_', $filename), rawurlencode($filename))
        );
        $response->setBody($content);
        return $response;
    }

    /** Dateiname: der ursprüngliche (mit .jpg, denn gespeichert ist immer ein JPEG), sonst foto-<ID>.jpg */
    private static function downloadFilename(Photo $photo): string
    {
        $base = pathinfo((string) $photo->OriginalName, PATHINFO_FILENAME);
        // Zeichen, die in Dateinamen nicht erlaubt sind
        $base = trim(preg_replace('#[\\\\/:*?"<>|\x00-\x1F]+#', '_', $base));
        return ($base !== '' ? $base : 'foto-' . $photo->ID) . '.jpg';
    }

    // ── Event-Seite ───────────────────────────────────────────────────────

    /** GET /api/v1/photos/eventPhotos/{eventID} — die neuesten Fotos eines Events plus Anzahl */
    public function eventPhotos(HTTPRequest $request): HTTPResponse
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
        if (!$org || !$org->exists() || !$org->EnablePhotos) {
            return $this->successResponse(['photos' => [], 'total' => 0, 'enabled' => false]);
        }

        $list = Photo::get()->filter(['OrganizationID' => $org->ID, 'Events.ID' => $event->ID]);
        return $this->successResponse([
            'photos'  => array_map(fn (Photo $p) => $this->formatSummary($p), $list->limit(8)->toArray()),
            'total'   => $list->count(),
            'enabled' => true,
        ]);
    }

    // ── Speicher-Einstellungen ────────────────────────────────────────────

    /**
     * Organisation, deren Speicher der Nutzer verwalten darf (ORG_MANAGE_SETTINGS).
     * @return array{0: ?Organization, 1: ?HTTPResponse}
     */
    private function settingsOrg(HTTPRequest $request, Member $member): array
    {
        $org = Organization::get()->byID((int) $request->param('ID'));
        if (!$org || !$member->isActiveMemberOfOrg($org)) {
            return [null, $this->errorResponse('Organisation nicht gefunden', 404)];
        }
        if (!$member->hasOrgPermission($org, OrgPermissions::ORG_MANAGE_SETTINGS)) {
            return [null, $this->errorResponse('Keine Berechtigung', 403)];
        }
        return [$org, null];
    }

    /** Formularwerte in eine (ungespeicherte) Konfiguration übernehmen; leere Schlüssel bleiben */
    private function applySettings(PhotoStorageConfig $config, array $body): ?string
    {
        $zone = trim((string) ($body['StorageZone'] ?? ''));
        if ($zone === '' || !preg_match('/^[A-Za-z0-9-]+$/', $zone)) {
            return 'Bitte den Namen der Storage Zone angeben (Buchstaben, Zahlen, Bindestriche)';
        }
        $region = (string) ($body['StorageRegion'] ?? '');
        if (!isset(PhotoStorageConfig::REGIONS[$region])) {
            return 'Bitte die Region der Storage Zone wählen';
        }
        $pullZone = rtrim(trim((string) ($body['PullZoneURL'] ?? '')), '/');
        if ($pullZone !== '' && !preg_match('#^https?://#i', $pullZone)) {
            $pullZone = 'https://' . $pullZone;
        }
        if (!filter_var($pullZone, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($pullZone), 'https://')) {
            return 'Bitte die Adresse der Pull Zone angeben, z.B. https://mein-verein.b-cdn.net';
        }

        $config->StorageZone = $zone;
        $config->StorageRegion = $region;
        $config->PullZoneURL = $pullZone;
        if (($key = trim((string) ($body['AccessKey'] ?? ''))) !== '') {
            $config->AccessKey = $key;
        }
        if (!empty($body['RemoveTokenKey'])) {
            $config->TokenKey = null;
        } elseif (($token = trim((string) ($body['TokenKey'] ?? ''))) !== '') {
            $config->TokenKey = $token;
        }
        if (!$config->AccessKey) {
            return 'Bitte das Passwort der Storage Zone angeben';
        }
        return null;
    }

    /**
     * GET  /api/v1/photos/settings/{orgID} — aktuelle Einstellungen (ohne Schlüssel)
     * PUT  /api/v1/photos/settings/{orgID} — speichern (leere Schlüssel = unverändert),
     *      vorher wird die Verbindung geprüft
     */
    public function settings(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        [$org, $error] = $this->settingsOrg($request, $member);
        if ($error) {
            return $error;
        }

        $config = PhotoStorageConfig::forOrganization((int) $org->ID)
            ?? PhotoStorageConfig::create(['OrganizationID' => $org->ID]);
        $usage = [
            'Count' => Photo::get()->filter('OrganizationID', $org->ID)->count(),
            'Bytes' => (int) Photo::get()->filter('OrganizationID', $org->ID)->sum('FileSize')
                + (int) Photo::get()->filter('OrganizationID', $org->ID)->sum('ThumbSize'),
        ];

        if ($request->httpMethod() === 'GET') {
            return $this->successResponse([
                'settings' => $config->toApi(),
                'regions'  => PhotoStorageConfig::REGIONS,
                'usage'    => $usage,
            ]);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        if ($message = $this->applySettings($config, $this->getJsonBody())) {
            return $this->errorResponse($message, 400);
        }
        if ($message = $config->client()->test()) {
            return $this->errorResponse($message, 400);
        }
        $config->write();

        return $this->successResponse([
            'settings' => $config->toApi(),
            'regions'  => PhotoStorageConfig::REGIONS,
            'usage'    => $usage,
        ], 'Foto-Speicher gespeichert');
    }

    /** POST /api/v1/photos/testSettings/{orgID} — Verbindung mit den Formularwerten prüfen, ohne zu speichern */
    public function testSettings(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        [$org, $error] = $this->settingsOrg($request, $member);
        if ($error) {
            return $error;
        }

        $saved = PhotoStorageConfig::forOrganization((int) $org->ID);
        $config = PhotoStorageConfig::create([
            'OrganizationID' => $org->ID,
            'AccessKey'      => $saved?->AccessKey,
            'TokenKey'       => $saved?->TokenKey,
        ]);
        if ($message = $this->applySettings($config, $this->getJsonBody())) {
            return $this->errorResponse($message, 400);
        }
        if ($message = $config->client()->test()) {
            return $this->errorResponse($message, 400);
        }
        return $this->successResponse([], 'Verbindung zu Bunny klappt');
    }
}
