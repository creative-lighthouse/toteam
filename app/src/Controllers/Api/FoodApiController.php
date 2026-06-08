<?php

namespace App\Controllers\Api;

use App\Calendar\Appointment;
use App\Food\Food;
use App\Food\Meal;
use App\Food\MealEater;
use App\Teams\OrganizationMembership;
use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Class \App\Controllers\Api\FoodApiController
 *
 */
class FoodApiController extends ApiController
{
    private static $url_segment = 'api/v1/food';

    private static $allowed_actions = [
        'index',
        'suggest',
        'mealdetail',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $orgIDs = $member->getOrganizationIDs();

            $canManage = OrganizationMembership::get()->filter([
                'MemberID' => $member->ID,
                'Role'     => ['moderator', 'admin'],
            ])->count() > 0;

            if (empty($orgIDs)) {
                return $this->jsonResponse([
                    'acceptedMeals' => [],
                    'otherMeals'    => [],
                    'pastMeals'     => [],
                    'myFoods'       => [],
                    'canManage'     => $canManage,
                ]);
            }

            $today = date('Y-m-d');

            // Index the member's meal RSVPs by meal ID
            $memberResponses = [];
            foreach (MealEater::get()->filter('MemberID', $member->ID) as $eater) {
                $memberResponses[$eater->ParentID] = $eater->Type;
            }

            // ── Upcoming meals (today and future) ──────────────────────────
            $acceptedMeals = [];
            $otherMeals    = [];

            $upcomingAppointments = Appointment::get()
                ->filter([
                    'Organisations.ID'              => $orgIDs,
                    'DateStart:GreaterThanOrEqual'   => $today,
                ])
                ->sort('DateStart ASC, TimeStart ASC');

            foreach ($upcomingAppointments as $appointment) {
                $org      = $appointment->Organisations()->first();
                $orgTitle = $org?->Title;
                $orgLogo  = ($org && $org->Logo()->exists())
                    ? $org->Logo()->ScaleWidth(80)->getURL()
                    : null;

                foreach ($appointment->Meals()->sort('Time ASC') as $meal) {
                    $data = $this->formatMeal($meal, $appointment, $orgTitle, $orgLogo, $memberResponses);

                    if (($memberResponses[$meal->ID] ?? null) === 'Accept') {
                        $acceptedMeals[] = $data;
                    } else {
                        $otherMeals[] = $data;
                    }
                }
            }

            // ── Past meals (before today) ───────────────────────────────────
            $pastMeals = [];

            $pastAppointments = Appointment::get()
                ->filter([
                    'Organisations.ID' => $orgIDs,
                    'DateStart:LessThan' => $today,
                ])
                ->sort('DateStart DESC, TimeStart DESC');

            foreach ($pastAppointments as $appointment) {
                $org      = $appointment->Organisations()->first();
                $orgTitle = $org?->Title;
                $orgLogo  = ($org && $org->Logo()->exists())
                    ? $org->Logo()->ScaleWidth(80)->getURL()
                    : null;

                foreach ($appointment->Meals()->sort('Time ASC') as $meal) {
                    $pastMeals[] = $this->formatMeal($meal, $appointment, $orgTitle, $orgLogo, $memberResponses);
                }
            }

            // ── My food suggestions ─────────────────────────────────────────
            $myFoods = [];

            foreach (Food::get()->filter('SupplierID', $member->ID) as $food) {
                foreach ($food->Meals() as $meal) {
                    $appointment = $meal->Parent();
                    if (!$appointment || !$appointment->exists()) {
                        continue;
                    }

                    // Only include meals from the user's organisations
                    $mealOrgIDs = $appointment->Organisations()->column('ID');
                    if (empty(array_intersect($mealOrgIDs, $orgIDs))) {
                        continue;
                    }

                    $org = $appointment->Organisations()->first();
                    $myFoods[] = [
                        'id'                  => $food->ID,
                        'title'               => $food->Title,
                        'preference'          => $food->FoodPreference ?: 'None',
                        'status'              => $food->Status ?: 'New',
                        'mealId'              => $meal->ID,
                        'mealTitle'           => $meal->Title,
                        'mealTime'            => $meal->RenderTime(),
                        'date'                => $appointment->DateStart,
                        'appointmentTitle'    => $appointment->Title,
                        'organizationTitle'   => $org?->Title,
                        'organizationLogoUrl' => ($org && $org->Logo()->exists())
                            ? $org->Logo()->ScaleWidth(80)->getURL()
                            : null,
                    ];
                }
            }

            // Sort by date descending (most recent first)
            usort($myFoods, fn ($a, $b) => strcmp($b['date'], $a['date']));

            return $this->jsonResponse([
                'acceptedMeals' => $acceptedMeals,
                'otherMeals'    => $otherMeals,
                'pastMeals'     => $pastMeals,
                'myFoods'       => $myFoods,
                'canManage'     => $canManage,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function suggest(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        try {
            $mealID = $request->param('ID');
            $meal   = Meal::get()->byID($mealID);

            if (!$meal || !$meal->exists()) {
                return $this->errorResponse('Mahlzeit nicht gefunden', 404);
            }

            if (!$meal->AcceptsContributions) {
                return $this->errorResponse('Diese Mahlzeit nimmt keine Vorschläge an', 403);
            }

            $appointment = $meal->Parent();
            $org         = $appointment->Organisations()->first();
            $orgIDs      = $member->getOrganizationIDs();

            if (!$org || !in_array($org->ID, $orgIDs)) {
                return $this->errorResponse('Zugriff verweigert', 403);
            }

            $data = $this->getJsonBody();

            if (empty($data['title'])) {
                return $this->errorResponse('Titel ist erforderlich', 400);
            }

            $allowedPrefs = ['None', 'Vegetarian', 'Vegan'];
            $preference   = in_array($data['preference'] ?? '', $allowedPrefs)
                ? $data['preference']
                : 'None';

            $food                 = Food::create();
            $food->Title          = $data['title'];
            $food->FoodPreference = $preference;
            $food->ParentID       = $org->ID;
            $food->SupplierID     = $member->ID;
            $food->Status         = 'New';
            $food->write();

            $food->Meals()->add($meal);

            $supplierName = trim($member->FirstName . ' ' . $member->Surname);

            return $this->successResponse([
                'food' => [
                    'id'         => $food->ID,
                    'title'      => $food->Title,
                    'preference' => $food->FoodPreference,
                    'status'     => 'New',
                    'supplier'   => $supplierName,
                ],
            ], 'Gericht vorgeschlagen');
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function mealdetail(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $mealID = $request->param('ID');
            $meal   = Meal::get()->byID($mealID);

            if (!$meal || !$meal->exists()) {
                return $this->errorResponse('Mahlzeit nicht gefunden', 404);
            }

            $appointment = $meal->Parent();
            $orgIDs      = $member->getOrganizationIDs();
            $mealOrgIDs  = $appointment->Organisations()->column('ID');

            if (empty(array_intersect($mealOrgIDs, $orgIDs))) {
                return $this->errorResponse('Zugriff verweigert', 403);
            }

            $eater           = MealEater::get()->filter(['MemberID' => $member->ID, 'ParentID' => $meal->ID])->first();
            $memberResponses = $eater ? [$meal->ID => $eater->Type] : [];

            $org      = $appointment->Organisations()->first();
            $orgTitle = $org?->Title;
            $orgLogo  = ($org && $org->Logo()->exists()) ? $org->Logo()->ScaleWidth(80)->getURL() : null;

            return $this->jsonResponse([
                'meal' => $this->formatMeal($meal, $appointment, $orgTitle, $orgLogo, $memberResponses),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    private function formatMeal(
        Meal $meal,
        Appointment $appointment,
        ?string $orgTitle,
        ?string $orgLogo,
        array $memberResponses
    ): array {
        $attendees = [];
        foreach ($meal->Eaters()->filter('Type', 'Accept') as $eater) {
            $m = $eater->Member();
            if (!$m || !$m->exists()) {
                continue;
            }
            $attendees[] = [
                'id'        => $m->ID,
                'name'      => trim($m->FirstName . ' ' . $m->Surname),
                'avatarUrl' => method_exists($m, 'getGravatar') ? $m->getGravatar() : null,
            ];
        }

        $foods = [];
        foreach ($meal->Foods() as $food) {
            $supplier = $food->Supplier();
            $foods[] = [
                'id'         => $food->ID,
                'title'      => $food->Title,
                'preference' => $food->FoodPreference ?: 'None',
                'status'     => $food->Status ?: 'New',
                'supplier'   => ($supplier && $supplier->exists())
                    ? trim($supplier->FirstName . ' ' . $supplier->Surname)
                    : null,
            ];
        }

        return [
            'id'                   => $meal->ID,
            'title'                => $meal->Title,
            'time'                 => $meal->RenderTime(),
            'acceptsContributions' => (bool) $meal->AcceptsContributions,
            'date'                 => $appointment->DateStart,
            'appointmentId'        => $appointment->ID,
            'appointmentTitle'     => $appointment->Title,
            'organizationTitle'    => $orgTitle,
            'organizationLogoUrl'  => $orgLogo,
            'userResponse'         => $memberResponses[$meal->ID] ?? null,
            'attendees'            => $attendees,
            'foods'                => $foods,
        ];
    }
}
