<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Dashboard API Controller
 *
 */
class DashboardApiController extends ApiController
{
    private static $url_segment = 'api/v1/dashboard';
    
    private static $allowed_actions = [
        'index'
    ];
    
    /**
     * Get dashboard data
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        
        // Get today's participations
        $todaysParticipations = [];
        if ($member->hasMethod('TodaysParticipations')) {
            foreach ($member->TodaysParticipations() as $participation) {
                $todaysParticipations[] = [
                    'ID' => $participation->ID,
                    'Parent' => [
                        'ID' => $participation->Parent()->ID,
                        'Title' => $participation->Parent()->Title,
                        'RenderTime' => $participation->Parent()->RenderTime(),
                        'Location' => $participation->Parent()->Location,
                        'Description' => $participation->Parent()->Description,
                    ]
                ];
            }
        }
        
        // Get upcoming events
        $upcomingEvents = [];
        if ($member->hasMethod('UpcomingEventDays')) {
            foreach ($member->UpcomingEventDays() as $eventDay) {
                $upcomingEvents[] = [
                    'ID' => $eventDay->ID,
                    'Title' => $eventDay->Title,
                    'RenderDateWithTime' => $eventDay->RenderDateWithTime(),
                    'Type' => $eventDay->Type
                ];
            }
        }
        
        // Get events without feedback
        $eventsWithoutFeedback = [];
        if ($member->hasMethod('EventDaysWithoutFeedback')) {
            foreach ($member->EventDaysWithoutFeedback() as $eventDay) {
                $eventsWithoutFeedback[] = [
                    'ID' => $eventDay->ID,
                    'RenderTitle' => $eventDay->RenderTitle(),
                    'RenderDateWithTime' => $eventDay->RenderDateWithTime()
                ];
            }
        }
        
        return $this->jsonResponse([
            'todaysParticipations' => $todaysParticipations,
            'upcomingEvents' => $upcomingEvents,
            'eventsWithoutFeedback' => $eventsWithoutFeedback
        ]);
    }
}
