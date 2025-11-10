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
            ->filter(['OwnerID' => $currentuser->ID])
            ->sort('Created', 'DESC')
            ->limit(5);
        $upcomingeventdays = EventDayParticipation::get()
            ->filter('MemberID', $currentuser->ID)
            ->filter(['Type' => ['Accept', 'Maybe']])
            ->filter('Parent.Date:GreaterThanOrEqual', date('Y-m-d'))
            ->sort('Parent.Date', 'ASC')
            ->limit(5);

        $eventDaysWithoutParticipation = EventDay::get()
            ->filter('Date:GreaterThanOrEqual', date('Y-m-d'))
            ->leftJoin(
                'EventDayParticipation',
                "\"EventDayParticipation\".\"ParentID\" = \"EventDay\".\"ID\" AND \"EventDayParticipation\".\"MemberID\" = {$currentuser->ID}"
            )
            ->where('"EventDayParticipation"."ID" IS NULL');


        $participationToday = EventDayParticipation::get()
            ->filter(['MemberID' => $currentuser->ID, 'Type' => 'Accept', 'Parent.Date' => date('Y-m-d')])->first();

        return $this->render([
            'User' => $currentuser,
            'LatestParticipations' => $latestparticipations,
            'LatestTasks' => $latesttasks,
            'UpcomingEventDays' => $upcomingeventdays,
            'EventDaysWithoutFeedback' => $eventDaysWithoutParticipation,
            'MealsToday' => $this->getAllMealsParticipatedToday(),
            'ParticipationToday' => $participationToday,
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
