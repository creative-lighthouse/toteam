<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Announcements\Announcement;
use App\Food\Food;
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
                        'LogoURL' => $org->RenderLogo(40),
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

        // Accepted food contributions in the next 3 days
        $myUpcomingContributions = [];
        if (!empty($organizationIDs)) {
            $today     = date('Y-m-d');
            $threeDays = date('Y-m-d', strtotime('+3 days'));

            foreach (Food::get()->filter(['SupplierID' => $member->ID, 'Status' => 'Accepted']) as $food) {
                foreach ($food->Meals() as $meal) {
                    $appointment = $meal->Parent();
                    if (!$appointment || !$appointment->exists()) {
                        continue;
                    }

                    $date = $appointment->DateStart;
                    if ($date < $today || $date > $threeDays) {
                        continue;
                    }

                    $mealOrgIDs = $appointment->Organisations()->column('ID');
                    if (empty(array_intersect($mealOrgIDs, $organizationIDs))) {
                        continue;
                    }

                    $org = $appointment->Organisations()->first();
                    $myUpcomingContributions[] = [
                        'foodId'              => $food->ID,
                        'foodTitle'           => $food->Title,
                        'foodPreference'      => $food->FoodPreference ?: 'None',
                        'mealId'              => $meal->ID,
                        'mealTitle'           => $meal->Title,
                        'mealTime'            => $meal->RenderTime(),
                        'date'                => $date,
                        'appointmentTitle'    => $appointment->Title,
                        'organizationTitle'   => $org?->Title,
                        'organizationLogoUrl' => $org?->RenderLogo(40),
                    ];
                }
            }

            usort(
                $myUpcomingContributions,
                fn ($a, $b) => strcmp($a['date'] . $a['mealTime'], $b['date'] . $b['mealTime'])
            );
        }

        return $this->jsonResponse([
            'latestAnnouncements'       => $latestAnnouncements,
            'newFeedback'               => $newFeedback,
            'myUpcomingContributions'   => $myUpcomingContributions,
        ]);
    }
}
