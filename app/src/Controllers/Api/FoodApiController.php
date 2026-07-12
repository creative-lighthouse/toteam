<?php

namespace App\Controllers\Api;

use App\Calendar\Appointment;
use App\Food\Food;
use App\Food\Meal;
use App\Food\MealEater;
use App\Food\MealProductOrder;
use App\Notifications\PushNotificationService;
use App\Teams\Organization;
use App\Teams\OrgPermissions;
use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Member;

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
        'mealDescription',
        'mealProduct',
        'mealProductOrder',
        'foodStatus',
        'pending',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $orgIDs = $member->getOrganizationIDs();

            $canManage  = $this->hasPermissionInAnyOrg($member, $orgIDs, OrgPermissions::FOOD_MANAGE_MEALS);
            $canApprove = $this->hasPermissionInAnyOrg($member, $orgIDs, OrgPermissions::FOOD_APPROVE_SUGGESTIONS);

            if (empty($orgIDs)) {
                return $this->jsonResponse([
                    'acceptedMeals' => [],
                    'otherMeals'    => [],
                    'pastMeals'     => [],
                    'myFoods'       => [],
                    'canManage'     => $canManage,
                    'canApprove'    => $canApprove,
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
                    $data = $this->formatMeal($meal, $appointment, $orgTitle, $orgLogo, $memberResponses, $member);

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
                    $pastMeals[] = $this->formatMeal($meal, $appointment, $orgTitle, $orgLogo, $memberResponses, $member);
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
                'canApprove'    => $canApprove,
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

            PushNotificationService::notifyFoodSuggestionPending($food, $meal);

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

            $canManage = $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::FOOD_MANAGE_MEALS);

            $mealData               = $this->formatMeal($meal, $appointment, $orgTitle, $orgLogo, $memberResponses, $member);
            $mealData['canManage']  = $canManage;

            return $this->jsonResponse(['meal' => $mealData]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function mealDescription(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $id   = (int) $request->param('ID');
        $meal = Meal::get()->byID($id);
        if (!$meal || !$meal->exists()) {
            return $this->errorResponse('Mahlzeit nicht gefunden', 404);
        }

        $appointment = $meal->Parent();
        $org = ($appointment && $appointment->exists()) ? $appointment->Organisations()->first() : null;
        if (!$org || !$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::FOOD_MANAGE_MEALS)) {
            return $this->errorResponse('Zugriff verweigert', 403);
        }

        $body = $this->getJsonBody();
        $meal->Description = trim($body['description'] ?? '');
        $meal->write();

        return $this->successResponse(['description' => $meal->Description], 'Beschreibung gespeichert');
    }

    public function mealProduct(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $id     = (int) $request->param('ID');
        $method = $request->httpMethod();

        if ($method === 'POST') {
            $meal = Meal::get()->byID($id);
            if (!$meal || !$meal->exists()) {
                return $this->errorResponse('Mahlzeit nicht gefunden', 404);
            }

            $mealAppointment = $meal->Parent();
            $mealOrg = ($mealAppointment && $mealAppointment->exists()) ? $mealAppointment->Organisations()->first() : null;
            if (!$mealOrg || !$mealOrg->exists() || !$member->hasOrgPermission($mealOrg, OrgPermissions::FOOD_MANAGE_MEALS)) {
                return $this->errorResponse('Zugriff verweigert', 403);
            }

            $body = $this->getJsonBody();
            if (empty($body['title'])) {
                return $this->errorResponse('Titel erforderlich', 400);
            }

            $appointment  = $meal->Parent();
            $org          = $appointment->Organisations()->first();

            $isOrderable = (bool) ($body['isOrderable'] ?? false);

            $food              = Food::create();
            $food->Title       = trim($body['title']);
            $food->IsOrderable = $isOrderable;
            $food->MaxQuantity = $isOrderable ? max(0, (int) ($body['maxQuantity'] ?? 0)) : 0;
            $food->Status      = 'Accepted';
            $food->ParentID    = $org?->ID ?? 0;
            $food->write();
            $food->Meals()->add($meal);

            return $this->successResponse([
                'product' => [
                    'id'           => $food->ID,
                    'title'        => $food->Title,
                    'isOrderable'  => $isOrderable,
                    'preference'   => $food->FoodPreference ?: 'None',
                    'status'       => 'Accepted',
                    'supplier'     => null,
                    'maxQuantity'  => (int) $food->MaxQuantity,
                    'totalOrdered' => 0,
                    'userQuantity' => 0,
                    'orders'       => [],
                ],
            ], 'Produkt hinzugefügt');
        }

        if ($method === 'PUT') {
            $food = Food::get()->byID($id);
            if (!$food || !$food->exists()) {
                return $this->errorResponse('Gericht nicht gefunden', 404);
            }

            $foodOrg = Organization::get()->byID((int) $food->ParentID);
            if (!$foodOrg || !$foodOrg->exists() || !$member->hasOrgPermission($foodOrg, OrgPermissions::FOOD_MANAGE_MEALS)) {
                return $this->errorResponse('Zugriff verweigert', 403);
            }

            $body = $this->getJsonBody();
            $title = trim($body['title'] ?? '');
            if (!$title) {
                return $this->errorResponse('Titel erforderlich', 400);
            }

            $isOrderable = (bool) ($body['isOrderable'] ?? $food->IsOrderable);

            $food->Title       = $title;
            $food->IsOrderable = $isOrderable;
            $food->MaxQuantity = $isOrderable ? max(0, (int) ($body['maxQuantity'] ?? $food->MaxQuantity)) : 0;
            $food->write();

            return $this->successResponse([
                'food' => [
                    'id'          => $food->ID,
                    'title'       => $food->Title,
                    'isOrderable' => $isOrderable,
                    'maxQuantity' => (int) $food->MaxQuantity,
                ],
            ], 'Gericht aktualisiert');
        }

        if ($method === 'DELETE') {
            $food = Food::get()->byID($id);
            if (!$food || !$food->exists()) {
                return $this->errorResponse('Produkt nicht gefunden', 404);
            }

            $foodOrg = Organization::get()->byID((int) $food->ParentID);
            if (!$foodOrg || !$foodOrg->exists() || !$member->hasOrgPermission($foodOrg, OrgPermissions::FOOD_MANAGE_MEALS)) {
                return $this->errorResponse('Zugriff verweigert', 403);
            }

            foreach ($food->Orders() as $order) {
                $order->delete();
            }
            $food->Meals()->removeAll();
            $food->delete();

            return $this->successResponse([], 'Produkt gelöscht');
        }

        return $this->errorResponse('Method not allowed', 405);
    }

    public function mealProductOrder(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $mealId = (int) $request->param('ID');
        $meal   = Meal::get()->byID($mealId);

        if (!$meal || !$meal->exists()) {
            return $this->errorResponse('Mahlzeit nicht gefunden', 404);
        }

        $eater = MealEater::get()->filter([
            'MemberID' => $member->ID,
            'ParentID' => $meal->ID,
            'Type'     => 'Accept',
        ])->first();

        if (!$eater) {
            return $this->errorResponse('Nur zugesagte Teilnehmer können Produkte bestellen', 403);
        }

        $body   = $this->getJsonBody();
        $orders = $body['orders'] ?? [];

        foreach ($meal->Foods()->filter('IsOrderable', true) as $food) {
            $quantity = max(0, (int) ($orders[$food->ID] ?? 0));
            if ($food->MaxQuantity > 0 && $quantity > $food->MaxQuantity) {
                $quantity = (int) $food->MaxQuantity;
            }

            $existing = MealProductOrder::get()->filter([
                'FoodID'   => $food->ID,
                'MealID'   => $meal->ID,
                'MemberID' => $member->ID,
            ])->first();

            if ($existing) {
                $existing->Quantity = $quantity;
                $existing->write();
            } elseif ($quantity > 0) {
                $order           = MealProductOrder::create();
                $order->FoodID   = $food->ID;
                $order->MealID   = $meal->ID;
                $order->MemberID = $member->ID;
                $order->Quantity = $quantity;
                $order->write();
            }
        }

        return $this->successResponse([], 'Bestellung gespeichert');
    }

    /** PUT /api/v1/food/foodStatus/$ID — offenen Vorschlag bestätigen/ablehnen */
    public function foodStatus(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $id   = (int) $request->param('ID');
        $food = Food::get()->byID($id);
        if (!$food || !$food->exists()) {
            return $this->errorResponse('Gericht nicht gefunden', 404);
        }

        if ($food->Status !== 'New') {
            return $this->errorResponse('Dieser Vorschlag wurde bereits bearbeitet', 400);
        }

        $foodOrg = Organization::get()->byID((int) $food->ParentID);
        if (!$foodOrg || !$foodOrg->exists() || !$member->hasOrgPermission($foodOrg, OrgPermissions::FOOD_APPROVE_SUGGESTIONS)) {
            return $this->errorResponse('Zugriff verweigert', 403);
        }

        $body   = $this->getJsonBody();
        $status = $body['status'] ?? '';
        if (!in_array($status, ['Accepted', 'Rejected'], true)) {
            return $this->errorResponse('Ungültiger Status', 400);
        }

        $food->Status = $status;
        $food->write();

        PushNotificationService::notifyFoodSuggestionDecision($food);

        return $this->successResponse(['id' => $food->ID, 'status' => $status], 'Vorschlag aktualisiert');
    }

    /** GET /api/v1/food/pending — offene Vorschläge über alle Organisationen mit FOOD_APPROVE_SUGGESTIONS */
    public function pending(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $orgIDs = array_values(array_filter(
            $member->getOrganizationIDs(),
            function ($orgID) use ($member) {
                $org = Organization::get()->byID($orgID);
                return $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::FOOD_APPROVE_SUGGESTIONS);
            }
        ));

        $pending = [];
        if (!empty($orgIDs)) {
            foreach (Food::get()->filter(['ParentID' => $orgIDs, 'Status' => 'New']) as $food) {
                foreach ($food->Meals() as $meal) {
                    $appointment = $meal->Parent();
                    if (!$appointment || !$appointment->exists()) {
                        continue;
                    }

                    $mealOrgIDs = $appointment->Organisations()->column('ID');
                    if (empty(array_intersect($mealOrgIDs, $orgIDs))) {
                        continue;
                    }

                    $org      = $appointment->Organisations()->first();
                    $supplier = $food->Supplier();

                    $pending[] = [
                        'id'                  => $food->ID,
                        'title'               => $food->Title,
                        'preference'          => $food->FoodPreference ?: 'None',
                        'supplier'            => ($supplier && $supplier->exists())
                            ? trim($supplier->FirstName . ' ' . $supplier->Surname)
                            : null,
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
        }

        usort($pending, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $this->jsonResponse(['pending' => $pending]);
    }

    /**
     * Baut die einheitliche Gerichte-Darstellung einer Mahlzeit: bestellbare und feste
     * Gerichte gemischt, jeweils mit isOrderable-Flag. Offene Vorschläge (Status=New)
     * werden nur an Nutzer mit FOOD_APPROVE_SUGGESTIONS ausgeliefert (für die Inline-
     * Bestätigung in mealdetail()), abgelehnte (Status=Rejected) nie — die sieht der
     * Vorschlagende bereits separat über "Meine Vorschläge" (myFoods).
     */
    private function formatMeal(
        Meal $meal,
        Appointment $appointment,
        ?string $orgTitle,
        ?string $orgLogo,
        array $memberResponses,
        Member $member
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
                'avatarUrl' => $m->hasMethod('RenderProfileImage') ? $m->RenderProfileImage() : null,
                'allergies' => $m->Allergies()->column('Title'),
            ];
        }

        $org        = $appointment->Organisations()->first();
        $canApprove = $org && $org->exists() && $member->hasOrgPermission($org, OrgPermissions::FOOD_APPROVE_SUGGESTIONS);

        $foods = [];
        foreach ($meal->Foods()->sort('ID ASC') as $food) {
            $status = $food->Status ?: 'New';
            if ($status === 'Rejected' || ($status === 'New' && !$canApprove)) {
                continue;
            }

            $supplier = $food->Supplier();
            $item = [
                'id'          => $food->ID,
                'title'       => $food->Title,
                'isOrderable' => (bool) $food->IsOrderable,
                'preference'  => $food->FoodPreference ?: 'None',
                'status'      => $status,
                'supplier'    => ($supplier && $supplier->exists())
                    ? trim($supplier->FirstName . ' ' . $supplier->Surname)
                    : null,
            ];

            if ($food->IsOrderable) {
                $perPerson = [];
                foreach (MealProductOrder::get()->filter([
                    'FoodID'               => $food->ID,
                    'MealID'               => $meal->ID,
                    'Quantity:GreaterThan' => 0,
                ])->sort('ID ASC') as $order) {
                    $m = $order->Member();
                    if ($m && $m->exists()) {
                        $perPerson[] = [
                            'memberId'  => $m->ID,
                            'name'      => trim($m->FirstName . ' ' . $m->Surname),
                            'avatarUrl' => $m->hasMethod('RenderProfileImage') ? $m->RenderProfileImage() : null,
                            'quantity'  => (int) $order->Quantity,
                        ];
                    }
                }
                $userOrder = MealProductOrder::get()->filter([
                    'FoodID'   => $food->ID,
                    'MealID'   => $meal->ID,
                    'MemberID' => $member->ID,
                ])->first();

                $item['maxQuantity']  = (int) $food->MaxQuantity;
                $item['totalOrdered'] = array_sum(array_column($perPerson, 'quantity'));
                $item['userQuantity'] = $userOrder ? (int) $userOrder->Quantity : 0;
                $item['orders']       = $perPerson;
            }

            $foods[] = $item;
        }

        return [
            'id'                   => $meal->ID,
            'title'                => $meal->Title,
            'description'          => $meal->Description ?: '',
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
            'canApprove'           => $canApprove,
        ];
    }
}
