<?php

namespace App\Pages;

use App\Events\EventDayMealEater;
use PageController;
use SilverStripe\Security\Security;
use App\Events\EventDayParticipation;
use App\Tasks\Task;

class DashboardPageController extends PageController
{
    private static $allowed_actions = [
    ];

    public function index()
    {
        $currentuser = Security::getCurrentUser();
        $registrationPage = RegistrationPage::get()->first();


        if (!$currentuser && $registrationPage) {
            return $this->redirect($registrationPage->Link());
        }

        $CalendarPage = CalendarPage::get()->first();
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

        return [
            'User' => $currentuser,
            'LatestParticipations' => $latestparticipations,
            'LatestTasks' => $latesttasks,
            'UpcomingEventDays' => $upcomingeventdays,
            'MealsToday' => $this->getAllMealsParticipatedToday(),
            'CalendarPage' => $CalendarPage,
            'ParticipationToday' => $participationToday,
        ];
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
