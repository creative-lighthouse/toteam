<?php

namespace App\Controllers\Api;

use App\Announcements\Announcement;
use App\Announcements\AnnouncementCategory;
use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Class \App\Controllers\Api\AnnouncementsApiController
 *
 */
class AnnouncementsApiController extends ApiController
{
    private static $url_segment = 'api/v1/announcements';

    private static $allowed_actions = [
        'index'
    ];

    protected function getDefaultAction()
    {
        return 'index';
    }

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $organizationIDs = $member->getOrganizationIDs();

            if (empty($organizationIDs)) {
                $categories = AnnouncementCategory::get();
                $categoriesData = [];
                foreach ($categories as $category) {
                    $categoriesData[] = ['ID' => $category->ID, 'Title' => $category->Title];
                }
                return $this->jsonResponse(['announcements' => [], 'categories' => $categoriesData]);
            }

            $announcements = Announcement::get()
                ->filter(['Organisations.ID' => $organizationIDs])
                ->distinct(true)
                ->sort('Created DESC');

            $announcementsData = [];
            foreach ($announcements as $announcement) {
                try {
                    $orgs = [];
                    foreach ($announcement->Organisations() as $org) {
                        $orgs[] = [
                            'ID' => $org->ID,
                            'Title' => $org->Title,
                            'LogoURL' => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(40)->getURL() : null,
                        ];
                    }

                    $announcementsData[] = [
                        'ID' => $announcement->ID,
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
                } catch (\Exception $e) {
                    error_log('Error processing announcement ' . $announcement->ID . ': ' . $e->getMessage());
                }
            }

            $categories = AnnouncementCategory::get();
            $categoriesData = [];
            foreach ($categories as $category) {
                $categoriesData[] = ['ID' => $category->ID, 'Title' => $category->Title];
            }

            return $this->jsonResponse([
                'announcements' => $announcementsData,
                'categories' => $categoriesData
            ]);
        } catch (\Exception $e) {
            error_log('AnnouncementsApiController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Error fetching announcements: ' . $e->getMessage(), 500);
        }
    }
}
