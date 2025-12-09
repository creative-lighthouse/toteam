<?php

namespace App\Controllers;

use App\Tasks\Task;
use App\Food\MealEater;
use App\Controllers\BaseController;
use App\Events\EventDay;
use SilverStripe\Security\Security;
use App\Events\EventDayParticipation;
use SilverStripe\ORM\DataList;

/**
 * Class \App\Controllers\DashboardController
 *
 */
class DashboardController extends BaseController
{
    private static $url_segment = 'dashboard';

    private static $allowed_actions = [
    ];

    public function index()
    {
        $currentuser = Security::getCurrentUser();

        if (!$currentuser) {
            return $this->redirect('registration');
        }

        $latestparticipations = EventDayParticipation::get()
            ->filter(['MemberID' => $currentuser->ID])
            ->sort('Created', 'DESC')
            ->limit(5);
        $latesttasks = Task::get()
            ->filter(['OwnerID' => $currentuser->ID]);
        // Filter tasks by user's organizations
        $latesttasks = $this->filterByUserOrganizations($latesttasks);
        $latesttasks = $latesttasks->sort('Created', 'DESC')
            ->limit(5);

        $organizationIDs = $this->getUserOrganizationIDs();
        if (!empty($organizationIDs)) {
            $upcomingeventdays = EventDayParticipation::get()
                ->filter('MemberID', $currentuser->ID)
                ->exclude(['Status' => ['Cancelled', 'Suggested']])
                ->filter(['Type' => ['Accept', 'Maybe']])
                ->filter('Parent.Date:GreaterThanOrEqual', date('Y-m-d'))
                ->filter('Parent.Parent.ParentID', $organizationIDs) // Filter by organization through Event
                ->sort('Parent.Date', 'ASC')
                ->limit(5);

            $eventDaysWithoutParticipation = EventDay::get()
                ->filter('Date:GreaterThanOrEqual', date('Y-m-d'))
                ->exclude('Status', 'Cancelled')
                ->filter('Parent.ParentID', $organizationIDs) // Filter by organization
                ->leftJoin(
                    'EventDayParticipation',
                    "\"EventDayParticipation\".\"ParentID\" = \"EventDay\".\"ID\" AND \"EventDayParticipation\".\"MemberID\" = {$currentuser->ID}"
                )
                ->where('"EventDayParticipation"."ID" IS NULL');
        } else {
            $upcomingeventdays = EventDayParticipation::get()->filter('ID', 0);
            $eventDaysWithoutParticipation = EventDay::get()->filter('ID', 0);
        }


        $participationToday = EventDayParticipation::get()
            ->filter(['MemberID' => $currentuser->ID, 'Type' => 'Accept', 'Parent.Date' => date('Y-m-d')])->first();

        $anycardsactive = false;
        if ($latesttasks->count() > 0) {
            $anycardsactive = true;
        } else if ($upcomingeventdays->count() > 0) {
            $anycardsactive = true;
        } else if ($eventDaysWithoutParticipation->count() > 0) {
            $anycardsactive = true;
        } else if ($this->getAllMealsParticipatedToday()->count() > 0) {
            $anycardsactive = true;
        }

        return $this->render([
            'User' => $currentuser,
            'LatestParticipations' => $latestparticipations,
            'LatestTasks' => $latesttasks,
            'UpcomingEventDays' => $upcomingeventdays,
            'EventDaysWithoutFeedback' => $eventDaysWithoutParticipation,
            'MealsToday' => $this->getAllMealsParticipatedToday(),
            'ParticipationToday' => $participationToday,
            'AnyCardsActive' => $anycardsactive,
        ]);
    }

    public function getAllMealsParticipatedToday()
    {
        $currentuser = Security::getCurrentUser();
        if (!$currentuser) {
            return null;
        }
        return MealEater::get()
            ->filter(['MemberID' => $currentuser->ID, 'Type' => 'Accept', 'Parent.Parent.Date' => date('Y-m-d')]);
    }

    public function getMealsWithoutFoodSupplied()
    {
        return FoodController::getMealsWithoutFood()->GroupedBy('ParentID');
    }
}
