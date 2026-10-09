<?php

namespace App\Controllers\Api;

use App\Calendar\Appointment;
use App\HumanResources\Allergy;
use App\Food\Food;
use App\Food\Meal;
use App\Food\MealEater;
use App\Food\MealProductOrder;
use App\Notifications\PushNotificationService;
use App\Teams\Organization;
use App\Teams\OrgEvent;
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
        'mealProduct',
        'mealProductOrder',
        'foodStatus',
        'suggestEvent',
        'planner',
        'assign',
        'plannerFood',
        'mealHistory',
    ];

    /** Wie viele vergangene Mahlzeiten die Übersicht höchstens mitliefert */
    private const PAST_MEALS_LIMIT = 50;

    /**
     * Übersicht des Essens-Totems.
     * GET /api/v1/food
     *
     * - upcomingMeals / pastMeals: kompakte Mahlzeiten-Karten (neueste vergangene zuerst)
     * - myFoods: eigene Vorschläge — an einer Mahlzeit oder (noch) nur an einem Event
     * - suggestEvents: Events mit kommenden Mahlzeiten, für die man vorschlagen kann
     * - planEvents: Events, deren Vorschläge man als Essensplaner zuordnen darf
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $orgIDs = $member->getOrganizationIDs();
            $empty  = [
                'upcomingMeals' => [],
                'pastMeals'     => [],
                'myFoods'       => [],
                'suggestEvents' => [],
                'planEvents'    => [],
            ];
            if (empty($orgIDs)) {
                return $this->jsonResponse($empty);
            }

            $today = date('Y-m-d');

            $memberResponses = [];
            foreach (MealEater::get()->filter('MemberID', $member->ID) as $eater) {
                $memberResponses[$eater->ParentID] = $eater->Type;
            }

            $upcomingMeals = [];
            $upcomingAppointments = Appointment::get()
                ->filter(['Organisations.ID' => $orgIDs, 'DateStart:GreaterThanOrEqual' => $today])
                ->sort('DateStart ASC, TimeStart ASC');
            foreach ($upcomingAppointments as $appointment) {
                foreach ($appointment->Meals()->sort('Time ASC') as $meal) {
                    $upcomingMeals[] = $this->formatMealListItem($meal, $appointment, $memberResponses);
                }
            }

            $pastMeals = [];
            $pastAppointments = Appointment::get()
                ->filter(['Organisations.ID' => $orgIDs, 'DateStart:LessThan' => $today])
                ->sort('DateStart DESC, TimeStart DESC');
            foreach ($pastAppointments as $appointment) {
                foreach ($appointment->Meals()->sort('Time DESC') as $meal) {
                    $pastMeals[] = $this->formatMealListItem($meal, $appointment, $memberResponses);
                    if (count($pastMeals) >= self::PAST_MEALS_LIMIT) {
                        break 2;
                    }
                }
            }

            // Events mit kommenden Mahlzeiten: für die kann man vorschlagen / planen
            $suggestEvents = [];
            $planEvents    = [];
            foreach (OrgEvent::get()->filter('OrganizationID', $orgIDs) as $event) {
                if (!$this->eventHasUpcomingMeals($event, $today)) {
                    continue;
                }
                $suggestEvents[] = $event->toApi();
                if ($member->hasOrgPermission($event->Organization(), OrgPermissions::FOOD_APPROVE_SUGGESTIONS)) {
                    $planEvents[] = $event->toApi();
                }
            }

            return $this->jsonResponse([
                'upcomingMeals' => $upcomingMeals,
                'pastMeals'     => $pastMeals,
                'myFoods'       => $this->formatMyFoods($member, $orgIDs),
                'suggestEvents' => $suggestEvents,
                'planEvents'    => $planEvents,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    private function eventHasUpcomingMeals(OrgEvent $event, string $today): bool
    {
        return Meal::get()->filter([
            'Parent.EventID'                   => $event->ID,
            'Parent.DateStart:GreaterThanOrEqual' => $today,
        ])->exists();
    }

    /** Kompakte Mahlzeit für die Liste: Kontext, Zähler und die Titel der geplanten Gerichte */
    private function formatMealListItem(Meal $meal, Appointment $appointment, array $memberResponses): array
    {
        $org   = $appointment->Organisations()->first();
        $event = $appointment->EventID ? $appointment->Event() : null;

        $foods = [];
        foreach ($meal->Foods()->filter('Status', 'Accepted')->sort('Title ASC') as $food) {
            $foods[] = $food->Title;
        }

        return [
            'id'                  => $meal->ID,
            'title'               => $meal->Title,
            'time'                => $meal->RenderTime(),
            'date'                => $appointment->DateStart,
            'appointmentId'       => $appointment->ID,
            'appointmentTitle'    => $appointment->Title,
            'eventTitle'          => $event && $event->exists() ? $event->Title : null,
            'organizationTitle'   => $org?->Title,
            'organizationLogoUrl' => $org?->RenderLogo(80),
            'acceptCount'         => $meal->Eaters()->filter('Type', 'Accept')->count(),
            'declineCount'        => $meal->Eaters()->filter('Type', 'Decline')->count(),
            'userResponse'        => $memberResponses[$meal->ID] ?? null,
            'foods'               => $foods,
        ];
    }

    /** Eigene Vorschläge, jeweils mit Mahlzeit (falls zugeordnet) oder nur mit Event */
    private function formatMyFoods(Member $member, array $orgIDs): array
    {
        $myFoods = [];
        foreach (Food::get()->filter(['SupplierID' => $member->ID, 'ParentID' => $orgIDs])->sort('Created DESC') as $food) {
            $meal        = $food->Meals()->first();
            $appointment = $meal ? $meal->Parent() : null;
            $event       = $food->EventID ? $food->Event() : ($appointment?->EventID ? $appointment->Event() : null);
            $org         = $food->Parent();

            $myFoods[] = [
                'id'                => $food->ID,
                'title'             => $food->Title,
                'preference'        => $food->FoodPreference ?: 'None',
                'status'            => $food->Status ?: 'New',
                'eventTitle'        => $event && $event->exists() ? $event->Title : null,
                'mealId'            => $meal?->ID,
                'mealTitle'         => $meal?->Title,
                'mealTime'          => $meal?->RenderTime(),
                'date'              => $appointment?->DateStart,
                'appointmentTitle'  => $appointment?->Title,
                'organizationTitle' => $org->exists() ? $org->Title : null,
            ];
        }
        return $myFoods;
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
            $food->EventID        = (int) $appointment->EventID;
            $food->write();

            $food->Meals()->add($meal);
            $meal->recordHistorySetChange('Foods', [$food], []);

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
            $orgLogo  = $org?->RenderLogo(80);

            // Termine können mehreren Orgs gehören — Recht in irgendeiner davon reicht
            $canManage = $this->hasPermissionInAnyOrg($member, $appointment->Organisations()->column('ID'), OrgPermissions::FOOD_MANAGE_MEALS);

            $mealData              = $this->formatMeal($meal, $appointment, $orgTitle, $orgLogo, $memberResponses, $member);
            $mealData['canManage'] = $canManage;

            return $this->jsonResponse(['meal' => $mealData]);
        } catch (\Exception $e) {
            return $this->errorResponse('Fehler: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Änderungsverlauf einer Mahlzeit inkl. Essens-Zu-/Absagen und Bestellungen.
     * GET /api/v1/food/mealHistory/:id?before=<EntryID>&limit=20
     */
    public function mealHistory(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $meal = Meal::get()->byID((int) $request->param('ID'));
        if (!$meal || !$meal->exists()) {
            return $this->errorResponse('Mahlzeit nicht gefunden', 404);
        }

        $appointment = $meal->Parent();
        $mealOrgIDs  = $appointment && $appointment->exists() ? $appointment->Organisations()->column('ID') : [];
        if (empty(array_intersect($mealOrgIDs, $member->getOrganizationIDs()))) {
            return $this->errorResponse('Zugriff verweigert', 403);
        }

        return $this->historyResponse($meal, $request);
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
            $mealOrgIDs = ($mealAppointment && $mealAppointment->exists()) ? $mealAppointment->Organisations()->column('ID') : [];
            if (!$this->hasPermissionInAnyOrg($member, $mealOrgIDs, OrgPermissions::FOOD_MANAGE_MEALS)) {
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
            if (in_array($body['preference'] ?? '', ['None', 'Vegetarian', 'Vegan'], true)) {
                $food->FoodPreference = $body['preference'];
            }
            if (!empty($body['supplierId'])) {
                $supplierError = $this->applySupplier($food, (int) $body['supplierId']);
                if ($supplierError) {
                    return $this->errorResponse($supplierError, 400);
                }
            }
            $food->write();
            $food->Meals()->add($meal);
            $meal->recordHistorySetChange('Foods', [$food], []);

            return $this->successResponse([
                'product' => [
                    'id'           => $food->ID,
                    'title'        => $food->Title,
                    'isOrderable'  => $isOrderable,
                    'preference'   => $food->FoodPreference ?: 'None',
                    'status'       => 'Accepted',
                    'supplier'     => $food->Supplier()->exists() ? $food->Supplier()->getDisplayName() : null,
                    'supplierId'   => $food->Supplier()->exists() ? $food->SupplierID : null,
                    'organizationId' => (int) $food->ParentID,
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

            // Mahlzeit-Verwalter und Essensplaner (die Gerichte auch als bestellbar markieren)
            if (!$this->canPlanFood($food, $member)) {
                return $this->errorResponse('Zugriff verweigert', 403);
            }

            $body = $this->getJsonBody();
            $title = trim($body['title'] ?? '');
            if (!$title) {
                return $this->errorResponse('Titel erforderlich', 400);
            }

            $isOrderable = (bool) ($body['isOrderable'] ?? $food->IsOrderable);

            $oldTitle       = (string) $food->Title;
            $oldIsOrderable = (bool) $food->IsOrderable;
            $oldMaxQuantity = (int) $food->MaxQuantity;

            $food->Title       = $title;
            $food->IsOrderable = $isOrderable;
            $food->MaxQuantity = $isOrderable ? max(0, (int) ($body['maxQuantity'] ?? $food->MaxQuantity)) : 0;
            if (in_array($body['preference'] ?? '', ['None', 'Vegetarian', 'Vegan'], true)) {
                $food->FoodPreference = $body['preference'];
            }
            if (array_key_exists('supplierId', $body)) {
                $supplierError = $this->applySupplier($food, (int) $body['supplierId']);
                if ($supplierError) {
                    return $this->errorResponse($supplierError, 400);
                }
            }
            $food->write();

            // Ein Gericht kann mehreren Mahlzeiten zugeordnet sein — in jedem Verlauf festhalten
            $maxLabel = fn (bool $orderable, int $max) => $orderable ? ($max > 0 ? (string) $max : 'Unbegrenzt') : null;
            foreach ($food->Meals() as $foodMeal) {
                $foodMeal->recordHistoryValueChange('Food#' . $food->ID . '.Title', 'Gericht', $oldTitle, $food->Title);
                $foodMeal->recordHistoryValueChange(
                    'Food#' . $food->ID . '.IsOrderable',
                    'Bestellbar (' . $food->Title . ')',
                    $oldIsOrderable ? 'Ja' : 'Nein',
                    $food->IsOrderable ? 'Ja' : 'Nein'
                );
                $foodMeal->recordHistoryValueChange(
                    'Food#' . $food->ID . '.MaxQuantity',
                    'Max. Menge (' . $food->Title . ')',
                    $maxLabel($oldIsOrderable, $oldMaxQuantity),
                    $maxLabel((bool) $food->IsOrderable, (int) $food->MaxQuantity)
                );
            }

            // Planer-Format (das Bearbeiten-Modal wird im Essensplaner und in der Mahlzeit genutzt)
            return $this->successResponse(['food' => $this->formatPlannerFood($food, $member)], 'Gericht aktualisiert');
        }

        if ($method === 'DELETE') {
            $food = Food::get()->byID($id);
            if (!$food || !$food->exists()) {
                return $this->errorResponse('Produkt nicht gefunden', 404);
            }

            // Mahlzeit-Verwalter und Essensplaner (die Gerichte auch als bestellbar markieren)
            if (!$this->canPlanFood($food, $member)) {
                return $this->errorResponse('Zugriff verweigert', 403);
            }

            // Erst aus dem Verlauf der Mahlzeiten entfernen, damit dort nicht zusätzlich
            // jede gelöschte Bestellung einzeln auftaucht
            foreach ($food->Meals() as $foodMeal) {
                $foodMeal->recordHistorySetChange('Foods', [], [$food]);
            }
            foreach ($food->Orders() as $order) {
                $order->MealID = 0;
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

        $oldStatus = $food->Status;
        $food->Status = $status;
        $food->write();

        $statusLabels = ['New' => 'Offen', 'Accepted' => 'Angenommen', 'Rejected' => 'Abgelehnt'];
        foreach ($food->Meals() as $foodMeal) {
            $foodMeal->recordHistoryValueChange(
                'Food#' . $food->ID . '.Status',
                'Vorschlag „' . $food->Title . '“',
                $statusLabels[$oldStatus] ?? $oldStatus,
                $statusLabels[$status]
            );
        }

        PushNotificationService::notifyFoodSuggestionDecision($food);

        return $this->successResponse(['id' => $food->ID, 'status' => $status], 'Vorschlag aktualisiert');
    }

    /**
     * Gericht für ein Event vorschlagen ("Das könnte ich mitbringen") — noch ohne
     * Mahlzeit; die legt der Essensplaner später per Zuordnen fest.
     *
     * Mit `asOrganization` legt ein Essensplaner ein Gericht an, das die
     * Organisation selbst stellt (ohne Person als Lieferant) — optional direkt
     * einer Mahlzeit des Events zugeordnet (dann gleich "Angenommen").
     *
     * POST /api/v1/food/suggestEvent/:eventId  Body: { title, preference, asOrganization?, mealId? }
     */
    public function suggestEvent(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $event = OrgEvent::get()->byID((int) $request->param('ID'));
        if (!$event || !in_array((int) $event->OrganizationID, array_map('intval', $member->getOrganizationIDs()), true)) {
            return $this->errorResponse('Event nicht gefunden', 404);
        }

        $data  = $this->getJsonBody();
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }
        $preference = in_array($data['preference'] ?? '', ['None', 'Vegetarian', 'Vegan'], true)
            ? $data['preference']
            : 'None';

        $asOrganization = !empty($data['asOrganization']);
        $meal           = null;
        if ($asOrganization) {
            if (!$member->hasOrgPermission($event->Organization(), OrgPermissions::FOOD_APPROVE_SUGGESTIONS)) {
                return $this->errorResponse('Nur die Essensplanung kann Gerichte für die Organisation anlegen', 403);
            }
            if (!empty($data['mealId'])) {
                $meal = Meal::get()->filter(['ID' => (int) $data['mealId'], 'Parent.EventID' => $event->ID])->first();
                if (!$meal) {
                    return $this->errorResponse('Mahlzeit gehört nicht zu diesem Event', 400);
                }
            }
        }

        $food                 = Food::create();
        $food->Title          = $title;
        $food->FoodPreference = $preference;
        $food->ParentID       = $event->OrganizationID;
        $food->EventID        = $event->ID;
        $food->SupplierID     = $asOrganization ? 0 : $member->ID;
        $food->Status         = $meal ? 'Accepted' : 'New';
        $food->write();

        if ($meal) {
            $food->Meals()->add($meal);
            $meal->recordHistorySetChange('Foods', [$food], []);
        }

        // Eigene Gerichte der Organisation legt die Essensplanung selbst an — keine Benachrichtigung
        if (!$asOrganization) {
            PushNotificationService::notifyFoodSuggestionForEvent($food, $event);
        }

        return $this->successResponse(
            ['food' => ['id' => $food->ID, 'title' => $food->Title, 'status' => $food->Status, 'mealId' => $meal?->ID]],
            $asOrganization ? 'Gericht angelegt' : 'Gericht vorgeschlagen'
        );
    }

    /**
     * Essensplaner: offene Vorschläge eines Events und seine kommenden Mahlzeiten
     * (nach Tag gruppiert) mit den bereits zugeordneten Gerichten.
     * GET /api/v1/food/planner/:eventId
     */
    public function planner(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $event = OrgEvent::get()->byID((int) $request->param('ID'));
        if (!$event || !$member->hasOrgPermission($event->Organization(), OrgPermissions::FOOD_APPROVE_SUGGESTIONS)) {
            return $this->errorResponse('Zugriff verweigert', 403);
        }

        $meals = Meal::get()
            ->filter(['Parent.EventID' => $event->ID, 'Parent.DateStart:GreaterThanOrEqual' => date('Y-m-d')])
            ->sort(['Parent.DateStart' => 'ASC', 'Time' => 'ASC']);
        $mealIDs = $meals->column('ID');

        // Offen: Vorschläge fürs Event (noch ohne Mahlzeit) und solche, die direkt an
        // einer Mahlzeit des Events gemacht wurden und noch nicht bestätigt sind
        $pool = [];
        $openFoods = Food::get()->filter('Status', 'New')->filterAny([
            'EventID'  => $event->ID,
            'Meals.ID' => $mealIDs ?: [-1],
        ])->sort('Created ASC');
        foreach ($openFoods as $food) {
            $suggestedMeal = $food->Meals()->first();
            $pool[] = $this->formatPlannerFood($food, $member) + [
                'suggestedMealId' => $suggestedMeal ? $suggestedMeal->ID : null,
            ];
        }

        $days = [];
        foreach ($meals as $meal) {
            $appointment = $meal->Parent();
            $date        = $appointment->DateStart;
            $foods       = [];
            foreach ($meal->Foods()->filter('Status', 'Accepted')->sort('Title ASC') as $food) {
                $foods[] = $this->formatPlannerFood($food, $member);
            }
            // Bearbeiten dürfen (wie im Termin) Termin- oder Mahlzeiten-Verwalter
            $apptOrgIDs = $appointment->Organisations()->column('ID');
            $canEdit    = $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::CALENDAR_MANAGE)
                || $this->hasPermissionInAnyOrg($member, $apptOrgIDs, OrgPermissions::FOOD_MANAGE_MEALS);

            $days[$date] ??= ['date' => $date, 'meals' => []];
            $days[$date]['meals'][] = [
                'id'               => $meal->ID,
                'title'            => $meal->Title,
                'time'             => $meal->RenderTime(),
                'description'      => $meal->Description ?: '',
                'acceptsContributions' => (bool) $meal->AcceptsContributions,
                'canEdit'          => $canEdit,
                'appointmentId'    => $appointment->ID,
                'appointmentTitle' => $appointment->Title,
                'acceptCount'      => $meal->Eaters()->filter('Type', 'Accept')->count(),
                'foods'            => $foods,
            ];
        }

        return $this->jsonResponse([
            'event' => $event->toApi(),
            'pool'  => $pool,
            'days'  => array_values($days),
        ]);
    }

    private function formatPlannerFood(Food $food, Member $member): array
    {
        $supplier = $food->Supplier();
        $org      = $food->Parent();
        return [
            // Bearbeiten/Löschen: Essensplanung der Organisation des Gerichts (auch bestellbare Produkte)
            'canEdit'     => $this->canPlanFood($food, $member),
            'id'          => $food->ID,
            'title'       => $food->Title,
            'preference'  => $food->FoodPreference ?: 'None',
            'supplier'    => $supplier->exists() ? $supplier->getDisplayName() : null,
            'supplierId'  => $supplier->exists() ? $supplier->ID : null,
            'organizationId' => (int) $food->ParentID,
            // Ohne Person stellt die Organisation das Gericht selbst
            'organizationTitle' => !$supplier->exists() && $org->exists() ? $org->Title : null,
            // Bestellbare Produkte gehören fest zu ihrer Mahlzeit — nicht verschiebbar
            'isOrderable' => (bool) $food->IsOrderable,
            'maxQuantity' => (int) $food->MaxQuantity,
        ];
    }

    /**
     * Setzt, wer ein Gericht mitbringt (0 = die Organisation stellt es). Eine neue Person
     * muss Mitglied der Organisation des Gerichts sein. Gibt im Fehlerfall die Meldung zurück.
     */
    private function applySupplier(Food $food, int $supplierID): ?string
    {
        if ($supplierID && $supplierID !== (int) $food->SupplierID) {
            $supplier = Member::get()->byID($supplierID);
            $membership = $supplier?->getMembershipInOrg($food->Parent());
            if (!$membership || $membership->Role !== 'member') {
                return 'Die Person ist kein Mitglied der Organisation';
            }
        }
        $food->SupplierID = $supplierID;
        return null;
    }

    /** Gericht bearbeiten/löschen: Essensplaner oder Mahlzeit-Verwalter der Organisation des Gerichts */
    private function canPlanFood(Food $food, Member $member): bool
    {
        $org = $food->Parent();
        return $org->exists() && (
            $member->hasOrgPermission($org, OrgPermissions::FOOD_APPROVE_SUGGESTIONS)
            || $member->hasOrgPermission($org, OrgPermissions::FOOD_MANAGE_MEALS)
        );
    }

    /**
     * Essensplaner: Gericht bearbeiten (Titel, Präferenz, wer es mitbringt) oder löschen.
     * PUT    /api/v1/food/plannerFood/:foodId  Body: { title, preference, supplierId? }
     *        supplierId: Mitglied der Organisation des Gerichts, null = die Organisation stellt es
     * DELETE /api/v1/food/plannerFood/:foodId
     */
    public function plannerFood(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $food = Food::get()->byID((int) $request->param('ID'));
        if (!$food || !$food->exists()) {
            return $this->errorResponse('Gericht nicht gefunden', 404);
        }
        if ($food->IsOrderable) {
            return $this->errorResponse('Bestellbare Produkte werden in der Mahlzeit bearbeitet', 400);
        }
        if (!$this->canPlanFood($food, $member)) {
            return $this->errorResponse('Zugriff verweigert', 403);
        }

        if ($request->httpMethod() === 'DELETE') {
            foreach ($food->Meals() as $meal) {
                $meal->recordHistorySetChange('Foods', [], [$food]);
            }
            $food->Meals()->removeAll();
            $food->delete();
            return $this->successResponse(['id' => (int) $request->param('ID')], 'Gericht gelöscht');
        }

        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body  = $this->getJsonBody();
        $title = trim($body['title'] ?? '');
        if ($title === '') {
            return $this->errorResponse('Titel ist erforderlich', 400);
        }
        $food->Title = $title;
        if (in_array($body['preference'] ?? '', ['None', 'Vegetarian', 'Vegan'], true)) {
            $food->FoodPreference = $body['preference'];
        }
        if (array_key_exists('supplierId', $body)) {
            $supplierError = $this->applySupplier($food, (int) $body['supplierId']);
            if ($supplierError) {
                return $this->errorResponse($supplierError, 400);
            }
        }
        $food->write();

        return $this->successResponse(['food' => $this->formatPlannerFood($food, $member)], 'Gericht gespeichert');
    }

    /**
     * Essensplaner: Vorschlag genau einer Mahlzeit zuordnen (→ Angenommen) oder
     * wieder zurück zu den offenen Vorschlägen legen (mealId null → Offen).
     * PUT /api/v1/food/assign/:foodId  Body: { mealId: int|null }
     */
    public function assign(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($request->httpMethod() !== 'PUT') {
            return $this->errorResponse('Method not allowed', 405);
        }

        $food = Food::get()->byID((int) $request->param('ID'));
        if (!$food || !$food->exists()) {
            return $this->errorResponse('Gericht nicht gefunden', 404);
        }
        if ($food->IsOrderable) {
            return $this->errorResponse('Bestellbare Produkte lassen sich nicht verschieben', 400);
        }
        $org = $food->Parent();
        if (!$org->exists() || !$member->hasOrgPermission($org, OrgPermissions::FOOD_APPROVE_SUGGESTIONS)) {
            return $this->errorResponse('Zugriff verweigert', 403);
        }

        $body   = $this->getJsonBody();
        $mealID = isset($body['mealId']) ? (int) $body['mealId'] : 0;
        $meal   = null;
        if ($mealID) {
            $meal = Meal::get()->byID($mealID);
            $appointment = $meal?->Parent();
            $mealOrgIDs  = $appointment ? array_map('intval', $appointment->Organisations()->column('ID')) : [];
            if (!$meal || !in_array((int) $org->ID, $mealOrgIDs, true)) {
                return $this->errorResponse('Mahlzeit nicht gefunden', 404);
            }
            // Vorschläge ohne Event übernehmen das Event der Mahlzeit
            if (!$food->EventID && $appointment->EventID) {
                $food->EventID = $appointment->EventID;
            }
        }

        $oldMeals  = $food->Meals()->toArray();
        $oldStatus = $food->Status;

        $food->Meals()->removeAll();
        if ($meal) {
            $food->Meals()->add($meal);
        }
        $food->Status = $meal ? 'Accepted' : 'New';
        $food->write();

        // Verlauf der betroffenen Mahlzeiten
        foreach ($oldMeals as $oldMeal) {
            if (!$meal || $oldMeal->ID !== $meal->ID) {
                $oldMeal->recordHistorySetChange('Foods', [], [$food]);
            }
        }
        if ($meal && !in_array($meal->ID, array_map(fn ($m) => $m->ID, $oldMeals), true)) {
            $meal->recordHistorySetChange('Foods', [$food], []);
        }

        if ($oldStatus === 'New' && $food->Status === 'Accepted') {
            PushNotificationService::notifyFoodSuggestionDecision($food);
        }

        return $this->successResponse(['id' => $food->ID, 'status' => $food->Status, 'mealId' => $meal?->ID], 'Zuordnung gespeichert');
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
                'username'  => $m->Username ?: null,
                'avatarUrl' => $m->hasMethod('RenderProfileImage') ? $m->RenderProfileImage() : null,
                'allergies' => $m->Allergies()->filter('Category', Allergy::CATEGORY_FOOD)->column('Title'),
                'preference' => $m->FoodPreference ?: 'None',
            ];
        }

        $declinedAttendees = [];
        foreach ($meal->Eaters()->filter('Type', 'Decline') as $eater) {
            $m = $eater->Member();
            if (!$m || !$m->exists()) {
                continue;
            }
            $declinedAttendees[] = [
                'id'        => $m->ID,
                'name'      => trim($m->FirstName . ' ' . $m->Surname),
                'username'  => $m->Username ?: null,
                'avatarUrl' => $m->hasMethod('RenderProfileImage') ? $m->RenderProfileImage() : null,
                'allergies' => $m->Allergies()->filter('Category', Allergy::CATEGORY_FOOD)->column('Title'),
                'preference' => $m->FoodPreference ?: 'None',
            ];
        }

        $pendingAttendees = [];
        foreach ($meal->getMembersWithoutResponse() as $m) {
            $pendingAttendees[] = [
                'id'        => $m->ID,
                'name'      => trim($m->FirstName . ' ' . $m->Surname),
                'username'  => $m->Username ?: null,
                'avatarUrl' => $m->hasMethod('RenderProfileImage') ? $m->RenderProfileImage() : null,
                'allergies' => $m->Allergies()->filter('Category', Allergy::CATEGORY_FOOD)->column('Title'),
                'preference' => $m->FoodPreference ?: 'None',
            ];
        }

        $canApprove    = $this->hasPermissionInAnyOrg(
            $member,
            $appointment->Organisations()->column('ID'),
            OrgPermissions::FOOD_APPROVE_SUGGESTIONS
        );
        $canRecordRsvp = $this->hasPermissionInAnyOrg(
            $member,
            $appointment->Organisations()->column('ID'),
            OrgPermissions::FOOD_RECORD_RSVP
        );

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
                'supplierId'  => ($supplier && $supplier->exists()) ? $supplier->ID : null,
                'organizationId' => (int) $food->ParentID,
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
            // Organisation, der neue Gerichte dieser Mahlzeit gehören (wie beim Anlegen in mealProduct())
            'organizationId'       => (int) ($appointment->Organisations()->first()?->ID ?? 0),
            'userResponse'         => $memberResponses[$meal->ID] ?? null,
            'attendees'            => $attendees,
            'declinedAttendees'    => $declinedAttendees,
            'pendingAttendees'     => $pendingAttendees,
            'foods'                => $foods,
            'canApprove'           => $canApprove,
            'canRecordRsvp'        => $canRecordRsvp,
        ];
    }
}
