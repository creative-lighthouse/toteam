<?php

namespace App\Controllers\Api;

use App\Announcements\Announcement;
use App\Announcements\AnnouncementCategory;
use App\Controllers\ApiController;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;

/**
 * Class \App\Controllers\Api\AnnouncementsApiController
 *
 */
class AnnouncementsApiController extends ApiController
{
    private static $url_segment = 'api/v1/announcements';

    private static $allowed_actions = [
        'index',
        'store',
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

    /**
     * GET /api/v1/announcements            → aktuell gültige Mitteilungen
     * GET /api/v1/announcements?archive=1  → abgelaufene Mitteilungen
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $organizationIDs = $member->getOrganizationIDs();

            if (empty($organizationIDs)) {
                return $this->jsonResponse([
                    'announcements' => [],
                    'categories' => $this->getCategoriesData(),
                    'createOrganizations' => [],
                ]);
            }

            $now = DBDatetime::now()->Rfc2822();
            $announcements = Announcement::get()
                ->filter(['Organisations.ID' => $organizationIDs])
                ->distinct(true);

            if ($request->getVar('archive')) {
                $announcements = $announcements
                    ->filter('ExpiryDate:LessThanOrEqual', $now)
                    ->sort('ExpiryDate DESC');
            } else {
                $announcements = $announcements
                    ->filterAny(['ReleaseDate' => null, 'ReleaseDate:LessThanOrEqual' => $now])
                    ->filterAny(['ExpiryDate' => null, 'ExpiryDate:GreaterThan' => $now])
                    ->sort('Created DESC');
            }

            $announcementsData = [];
            foreach ($announcements as $announcement) {
                try {
                    $announcementsData[] = $this->formatAnnouncement($announcement);
                } catch (\Exception $e) {
                    error_log('Error processing announcement ' . $announcement->ID . ': ' . $e->getMessage());
                }
            }

            return $this->jsonResponse([
                'announcements' => $announcementsData,
                'categories' => $this->getCategoriesData(),
                'createOrganizations' => $this->getCreateOrganizationsData($member),
            ]);
        } catch (\Exception $e) {
            error_log('AnnouncementsApiController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Error fetching announcements: ' . $e->getMessage(), 500);
        }
    }

    /** POST /api/v1/announcements/store */
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
        $title = trim($body['Title'] ?? '');
        if (!$title) {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }

        $orgIDs = array_unique(array_filter(array_map('intval', (array) ($body['OrganizationIDs'] ?? []))));
        if (empty($orgIDs)) {
            return $this->errorResponse('Mindestens eine Organisation ist erforderlich', 400);
        }

        $orgs = [];
        foreach ($orgIDs as $orgID) {
            $org = Organization::get()->byID($orgID);
            if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::ANNOUNCEMENTS_CREATE)) {
                return $this->errorResponse('Keine Berechtigung für diese Organisation', 403);
            }
            $orgs[] = $org;
        }

        $releaseDate = $this->parseDateTime($body['ReleaseDate'] ?? null);
        $expiryDate = $this->parseDateTime($body['ExpiryDate'] ?? null);
        if ($releaseDate === false || $expiryDate === false) {
            return $this->errorResponse('Ungültiges Datum', 400);
        }
        if ($releaseDate && $expiryDate && $expiryDate <= $releaseDate) {
            return $this->errorResponse('Das Ablaufdatum muss nach dem Veröffentlichungsdatum liegen', 400);
        }

        $categoryID = (int) ($body['CategoryID'] ?? 0);
        if ($categoryID && !AnnouncementCategory::get()->byID($categoryID)) {
            return $this->errorResponse('Kategorie nicht gefunden', 400);
        }

        try {
            $announcement = Announcement::create();
            $announcement->Title = $title;
            $announcement->ShortText = trim($body['ShortText'] ?? '');
            $announcement->LongText = $this->plainTextToHtml($body['LongText'] ?? '');
            $announcement->ReleaseDate = $releaseDate;
            $announcement->ExpiryDate = $expiryDate;
            $announcement->CategoryID = $categoryID;
            $announcement->AuthorID = $member->ID;
            $announcement->write();

            foreach ($orgs as $org) {
                $announcement->Organisations()->add($org);
            }

            return $this->successResponse(['announcement' => $this->formatAnnouncement($announcement)], 'Mitteilung erstellt');
        } catch (\Exception $e) {
            error_log('AnnouncementsApiController::store error: ' . $e->getMessage());
            return $this->errorResponse('Fehler beim Erstellen der Mitteilung', 500);
        }
    }

    /**
     * Organisationen, in denen der Nutzer Mitteilungen erstellen darf (für die Auswahl im Erstellen-Dialog).
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

    private function getCategoriesData(): array
    {
        $data = [];
        foreach (AnnouncementCategory::get() as $category) {
            $data[] = ['ID' => $category->ID, 'Title' => $category->Title];
        }
        return $data;
    }

    /**
     * Wandelt den Klartext aus dem Formular in einfaches HTML um: Leerzeilen trennen Absätze,
     * einfache Zeilenumbrüche werden zu <br>.
     */
    private function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim(str_replace("\r\n", "\n", $text)));
        $html = '';
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }
            $html .= '<p>' . nl2br(Convert::raw2xml(trim($paragraph)), false) . '</p>';
        }
        return $html;
    }

    private function formatAnnouncement(Announcement $announcement): array
    {
        $orgs = [];
        foreach ($announcement->Organisations() as $org) {
            $orgs[] = [
                'ID' => $org->ID,
                'Title' => $org->Title,
                'LogoURL' => $org->RenderLogo(40),
            ];
        }

        $now = DBDatetime::now()->getTimestamp();
        if ($announcement->ExpiryDate && strtotime($announcement->ExpiryDate) <= $now) {
            $status = 'expired';
        } elseif ($announcement->ReleaseDate && strtotime($announcement->ReleaseDate) > $now) {
            $status = 'scheduled';
        } else {
            $status = 'active';
        }

        return [
            'ID' => $announcement->ID,
            'Status' => $status,
            'Title' => $announcement->Title,
            'ShortText' => $announcement->ShortText,
            'LongText' => $announcement->LongText,
            'Created' => $announcement->dbObject('Created')->Nice(),
            'ReleaseDate' => $announcement->ReleaseDate ? $announcement->dbObject('ReleaseDate')->Nice() : null,
            'ExpiryDate' => $announcement->ExpiryDate ? $announcement->dbObject('ExpiryDate')->Nice() : null,
            'CategoryID' => $announcement->CategoryID,
            'Category' => $announcement->Category()->exists() ? [
                'ID' => $announcement->Category()->ID,
                'Title' => $announcement->Category()->Title
            ] : null,
            'AuthorName' => $announcement->Author()->exists()
                ? trim($announcement->Author()->FirstName . ' ' . $announcement->Author()->Surname)
                : null,
            'Organisations' => $orgs,
        ];
    }
}
