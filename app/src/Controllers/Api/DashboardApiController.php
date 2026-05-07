<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Announcements\Announcement;
use App\SuggestionBox\Suggestion;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Class \App\Controllers\Api\DashboardApiController
 *
 */
class DashboardApiController extends ApiController
{
    private static $url_segment = 'api/v1/dashboard';

    private static $allowed_actions = [
        'index'
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();

        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $organizationIDs = $member->getOrganizationIDs();

        // Latest notices for the user's organisations (max 2)
        $latestAnnouncements = [];
        if (!empty($organizationIDs)) {
            $announcements = Announcement::get()
                ->filter(['Organisations.ID' => $organizationIDs])
                ->distinct(true)
                ->sort('Created DESC')
                ->limit(2);
            foreach ($announcements as $announcement) {
                $orgs = [];
                foreach ($announcement->Organisations() as $org) {
                    $orgs[] = [
                        'ID' => $org->ID,
                        'Title' => $org->Title,
                        'LogoURL' => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(40)->getURL() : null,
                    ];
                }

                $latestAnnouncements[] = [
                    'ID' => $announcement->ID,
                    'Title' => $announcement->Title,
                    'ShortText' => $announcement->ShortText,
                    'Created' => $announcement->dbObject('Created')->Nice(),
                    'Category' => $announcement->Category()->exists() ? $announcement->Category()->Title : null,
                    'AuthorName' => $announcement->Author()->exists() ? $announcement->Author()->FirstName : null,
                    'Organisations' => $orgs,
                ];
            }
        }

        // Unseen feedback addressed to the current user
        $newFeedback = [];
        $suggestions = Suggestion::get()
            ->filter([
                'RecipientID' => $member->ID,
                'SeenByRecipient' => false,
            ])
            ->sort('Created DESC')
            ->limit(5);
        foreach ($suggestions as $suggestion) {
            $newFeedback[] = [
                'ID' => $suggestion->ID,
                'Title' => $suggestion->Title,
                'Description' => $suggestion->Description,
                'Created' => $suggestion->Created,
                'IsAnonymous' => (bool) $suggestion->IsAnonymous,
                'SenderName' => !$suggestion->IsAnonymous && $suggestion->Sender()->exists()
                    ? $suggestion->Sender()->FirstName
                    : null,
            ];
        }

        return $this->jsonResponse([
            'latestAnnouncements' => $latestAnnouncements,
            'newFeedback' => $newFeedback,
        ]);
    }
}
