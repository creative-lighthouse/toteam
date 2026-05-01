<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Notices\Notice;
use App\Notices\NoticeCategory;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Notices API Controller
 *
 */
class NoticesApiController extends ApiController
{
    private static $url_segment = 'api/v1/notices';

    private static $allowed_actions = [
        'index'
    ];

    /**
     * Default action - serves as index when no action specified
     */
    protected function getDefaultAction()
    {
        return 'index';
    }

    /**
     * Get notices for the current user's organisations
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
                $categories = NoticeCategory::get();
                $categoriesData = [];
                foreach ($categories as $category) {
                    $categoriesData[] = ['ID' => $category->ID, 'Title' => $category->Title];
                }
                return $this->jsonResponse(['notices' => [], 'categories' => $categoriesData]);
            }

            $notices = Notice::get()
                ->filter(['Organisations.ID' => $organizationIDs])
                ->distinct(true)
                ->sort('Created DESC');

            $noticesData = [];
            foreach ($notices as $notice) {
                try {
                    $noticesData[] = [
                        'ID' => $notice->ID,
                        'Title' => $notice->Title,
                        'ShortText' => $notice->ShortText,
                        'LongText' => $notice->LongText,
                        'Created' => $notice->dbObject('Created')->Nice(),
                        'ReleaseDate' => $notice->ReleaseDate ? $notice->dbObject('ReleaseDate')->Nice() : null,
                        'ExpiryDate' => $notice->ExpiryDate ? $notice->dbObject('ExpiryDate')->Nice() : null,
                        'CategoryID' => $notice->CategoryID,
                        'Category' => $notice->Category()->exists() ? [
                            'ID' => $notice->Category()->ID,
                            'Title' => $notice->Category()->Title
                        ] : null,
                    ];
                } catch (\Exception $e) {
                    error_log('Error processing notice ' . $notice->ID . ': ' . $e->getMessage());
                }
            }

            $categories = NoticeCategory::get();
            $categoriesData = [];
            foreach ($categories as $category) {
                $categoriesData[] = ['ID' => $category->ID, 'Title' => $category->Title];
            }

            return $this->jsonResponse([
                'notices' => $noticesData,
                'categories' => $categoriesData
            ]);
        } catch (\Exception $e) {
            error_log('NoticesApiController error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Error fetching notices: ' . $e->getMessage(), 500);
        }
    }
}

