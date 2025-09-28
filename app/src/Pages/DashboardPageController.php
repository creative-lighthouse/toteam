<?php

namespace App\Pages;

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
        $participationPage = ParticipationPage::get()->first();
        $currentuser = Security::getCurrentUser();
        $latestparticipations = EventDayParticipation::get()
            ->filter(['MemberID' => $currentuser->ID])
            ->sort('Created', 'DESC')
            ->limit(5);
        $latesttasks = Task::get()
            ->filter(['OwnerID' => $currentuser->ID])
            ->sort('Created', 'DESC')
            ->limit(5);
        $upcomingeventdays = EventDayParticipation::get()
            ->filter(['MemberID' => $currentuser->ID, 'Type' => 'Accept'])
            ->sort('Parent.Date', 'ASC')
            ->limit(5);
        $participationToday = EventDayParticipation::get()
            ->filter(['MemberID' => $currentuser->ID, 'Type' => 'Accept', 'Parent.Date' => date('Y-m-d')])->first();
        return [
            'User' => $currentuser,
            'LatestParticipations' => $latestparticipations,
            'LatestTasks' => $latesttasks,
            'UpcomingEventDays' => $upcomingeventdays,
            'ParticipationPage' => $participationPage,
            'ParticipationToday' => $participationToday,
        ];
    }
}
