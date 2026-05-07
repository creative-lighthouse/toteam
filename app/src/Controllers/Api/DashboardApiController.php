<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Notices\Notice;
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
        $latestNotices = [];
        if (!empty($organizationIDs)) {
            $notices = Notice::get()
                ->filter(['Organisations.ID' => $organizationIDs])
                ->distinct(true)
                ->sort('Created DESC')
                ->limit(2);
            foreach ($notices as $notice) {
                $orgs = [];
                foreach ($notice->Organisations() as $org) {
                    $orgs[] = [
                        'ID' => $org->ID,
                        'Title' => $org->Title,
                        'LogoURL' => $org->Logo()->exists() ? $org->Logo()->ScaleWidth(40)->getURL() : null,
                    ];
                }

                $latestNotices[] = [
                    'ID' => $notice->ID,
                    'Title' => $notice->Title,
                    'ShortText' => $notice->ShortText,
                    'Created' => $notice->dbObject('Created')->Nice(),
                    'Category' => $notice->Category()->exists() ? $notice->Category()->Title : null,
                    'AuthorName' => $notice->Author()->exists() ? $notice->Author()->FirstName : null,
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
            'latestNotices' => $latestNotices,
            'newFeedback' => $newFeedback,
        ]);
    }
}
