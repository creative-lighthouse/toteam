<?php

namespace App\Controllers;

use App\Tasks\Task;
use App\Pages\RegistrationPage;
use App\Events\EventDayMealEater;
use App\Controllers\BaseController;
use SilverStripe\Security\Security;
use App\Events\EventDayParticipation;

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
        $participationToday = EventDayParticipation::get()
            ->filter(['MemberID' => $currentuser->ID, 'Type' => 'Accept', 'Parent.Date' => date('Y-m-d')])->first();

        return $this->render([
            'User' => $currentuser,
            'LatestParticipations' => $latestparticipations,
            'LatestTasks' => $latesttasks,
            'UpcomingEventDays' => $upcomingeventdays,
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
        return EventDayMealEater::get()
            ->filter(['MemberID' => $currentuser->ID, 'Type' => 'Accept', 'Parent.Parent.Date' => date('Y-m-d')]);
    }
}
