<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Food\Food;
use App\Skript\ScriptRoleAssignment;
use App\SuggestionBox\Suggestion;
use App\Tasks\Task;
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

        // Unfinished tasks (incl. subtasks) where the user is owner or supporter,
        // soonest deadline first — tasks without a deadline go last.
        $myTasks = [];
        if (!empty($organizationIDs)) {
            $baseFilter = [
                'OrganizationID' => $organizationIDs,
                'State:not'      => 'finished',
            ];
            $ownedIDs     = Task::get()->filter($baseFilter + ['OwnerID' => $member->ID])->column('ID');
            $supportedIDs = Task::get()->filter($baseFilter + ['Supporters.ID' => $member->ID])->column('ID');
            $taskIDs      = array_unique(array_merge($ownedIDs, $supportedIDs));

            if (!empty($taskIDs)) {
                $tasks = Task::get()->byIDs($taskIDs)->toArray();
                usort($tasks, function (Task $a, Task $b) {
                    if (!$a->Deadline || !$b->Deadline) {
                        return $a->Deadline ? -1 : ($b->Deadline ? 1 : $b->ID <=> $a->ID);
                    }
                    return strcmp($a->Deadline, $b->Deadline);
                });

                foreach (array_slice($tasks, 0, 3) as $task) {
                    $org    = $task->Organization();
                    $parent = $task->Parent();
                    $myTasks[] = [
                        'ID'           => $task->ID,
                        'Hash'         => $task->Hash,
                        'Title'        => $task->Title,
                        'State'        => $task->State ?: 'open',
                        'Deadline'     => $task->Deadline,
                        'DeadlineNice' => $task->Deadline ? $task->dbObject('Deadline')->Date() : null,
                        'Parent'       => $parent && $parent->exists() ? [
                            'ID'    => $parent->ID,
                            'Title' => $parent->Title,
                        ] : null,
                        'Organization' => $org && $org->exists() ? [
                            'ID'      => $org->ID,
                            'Title'   => $org->Title,
                            'LogoURL' => $org->RenderLogo(40),
                        ] : null,
                    ];
                }
            }
        }

        // Rollen, die der Nutzer heute spielt (Rollenplan der Events) — das Dashboard
        // zeigt sie unter den heutigen Terminen desselben Events
        $myRolesToday = [];
        $assignments = ScriptRoleAssignment::get()->filter([
            'MemberID' => $member->ID,
            'Date'     => date('Y-m-d'),
        ]);
        foreach ($assignments as $assignment) {
            $role   = $assignment->Role();
            $script = $role->Script();
            if (!$role->exists() || !$script->exists()) {
                continue;
            }
            $myRolesToday[] = [
                'ID'          => $assignment->ID,
                'EventID'     => (int) $assignment->EventID,
                'RoleTitle'   => $role->Title,
                'ScriptTitle' => $script->Title,
                'ScriptHash'  => $script->Hash,
                'TimeStart'   => $assignment->TimeStart ? substr($assignment->TimeStart, 0, 5) : null,
                'TimeEnd'     => $assignment->TimeEnd ? substr($assignment->TimeEnd, 0, 5) : null,
            ];
        }

        return $this->jsonResponse([
            'myTasks'                   => $myTasks,
            'myRolesToday'              => $myRolesToday,
            'newFeedback'               => $newFeedback,
            'myUpcomingContributions'   => $myUpcomingContributions,
        ]);
    }
}
