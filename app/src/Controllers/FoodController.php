<?php

namespace App\Controllers;

use App\Food\Food;
use App\Food\Meal;
use App\HumanResources\Allergy;
use App\Controllers\BaseController;
use PHPUnit\Framework\Attributes\Group;
use SilverStripe\Security\Security;
use SilverStripe\Model\List\GroupedList;
use SilverStripe\ORM\DataList;

/**
 * Class \App\Controllers\FoodController
 *
 */
class FoodController extends BaseController
{
    private static $url_segment = 'food';

    private static $allowed_actions = [
        'meal',
        'addfood',
        'editfood',
    ];

    public function index()
    {
        //Only show meals today or in the future
        $allmeals = Meal::get()->sort('ParentID', 'DESC')->sort('Time', 'ASC');
        $meals = $allmeals->filter('Parent.Date:GreaterThanOrEqual', date('Y-m-d'));

        $mealswithoutfood = DataList::create(Meal::class);
        foreach ($meals as $meal) {
            if ($meal->Foods()->count() == 0) {
                $mealswithoutfood->add($meal);
            }
        }

        //Filter mealswithoutfood for future date
        $mealswithoutfood = $mealswithoutfood->filter('Parent.Date:GreaterThanOrEqual', date('Y-m-d'));

        $usersuppliedfoods = [];
        $currentuser = Security::getCurrentUser();
        if ($currentuser) {
            $usersuppliedfoods = Food::get()
                ->filter('SupplierID', $currentuser->ID);
        }
        //Filter usersuppliedfoods for meals in the future
        $usersuppliedfoods = $usersuppliedfoods->filter('Meals.Parent.Date:GreaterThanOrEqual', date('Y-m-d'));
        //Sort usersuppliedfoods by Status and Title
        $usersuppliedfoods = $usersuppliedfoods->sort(['Status' => 'ASC', 'Title' => 'ASC']);

        //Sortiere nach Parent.Date und Time gleichzeitig
        $meals = $meals->sort(['Parent.Date' => 'ASC', 'Time' => 'ASC']);
        $mealswithoutfood = $mealswithoutfood->sort(['Parent.Date' => 'ASC', 'Time' => 'ASC']);

        //Create grouped lists
        $mealswithoutfood = GroupedList::create($mealswithoutfood);
        $meals = GroupedList::create($meals);

        return $this->render([
            'Meals' => $meals,
            'MealsWithoutFood' => $mealswithoutfood,
            'UserSuppliedFoods' => $usersuppliedfoods,
        ]);
    }

    public function getAllAllergies()
    {
        return Allergy::get()->sort('Title', 'ASC');
    }

    public function meal($request)
    {
        $mealID = $request->param('ID');
        $meal = Meal::get()->byID($mealID);
        if (!$meal) {
            return $this->httpError(404, 'Meal not found');
        }

        return $this->render([
            'Meal' => $meal,
        ]);
    }

    public function addfood($request)
    {
        //Add a new food to a meal on post request
        if ($request->isPOST()) {
            $mealID = $request->postVar('mealid');
            $meal = Meal::get()->byID($mealID);
            if (!$meal) {
                return $this->httpError(404, 'Meal not found');
            }

            // Create a new food item
            $food = new Food();
            $food->Title = $request->postVar('title');
            $food->Notes = $request->postVar('notes');
            $food->FoodPreference = $request->postVar('foodpreference');
            $food->SupplierID = Security::getCurrentUser()->ID;
            $food->Meals()->add($meal);

            $allergyIDs = $request->postVar('allergies'); // This should be an array of allergy IDs
            if (is_array($allergyIDs)) {
                $allergies = Allergy::get()->filter('ID', $allergyIDs);
                if ($allergies->count() > 0) {
                    $food->Allergies()->addMany($allergies);
                }
            }

            // Save the food item
            if ($food->write()) {
                //Add food Item to meal
                return $this->redirect($meal->getDetailsLink());
            } else {
                return $this->httpError(500, 'Could not save food item');
            }
        }

        return $this->httpError(400, 'Invalid request');
    }

    public function editfood($request)
    {
        //Edit an existing food item on post request
        if ($request->isPOST()) {
            $foodID = $request->postVar('foodid');
            $food = Food::get()->byID($foodID);
            if (!$food) {
                return $this->httpError(404, 'Food not found');
            }

            $mealID = $request->postVar('mealid');
            $meal = Meal::get()->byID($mealID);
            if (!$meal) {
                return $this->httpError(404, 'Meal not found');
            }

            // Update food item
            $food->Title = $request->postVar('title');
            $food->Notes = $request->postVar('notes');
            $food->FoodPreference = $request->postVar('foodpreference');

            $allergyIDs = $request->postVar('allergies'); // This should be an array of allergy IDs
            // Clear existing allergies
            $food->Allergies()->removeAll();
            if (is_array($allergyIDs)) {
                $allergies = Allergy::get()->filter('ID', $allergyIDs);
                if ($allergies->count() > 0) {
                    $food->Allergies()->addMany($allergies);
                }
            }

            // Save the food item
            if ($food->write()) {
                return $this->redirectBack();
            } else {
                return $this->httpError(500, 'Could not save food item');
            }
        }

        return $this->httpError(400, 'Invalid request');
    }

    public function FoodAddLink()
    {
        return 'food/addfood';
    }

    public function FoodEditLink()
    {
        return 'food/editfood';
    }

    public static function getMealsWithoutFood()
    {

        $allmeals = Meal::get()->sort('ParentID', 'DESC')->sort('Time', 'ASC');
        $meals = $allmeals->filter('Parent.Date:GreaterThanOrEqual', date('Y-m-d'));

        $mealswithoutfood = DataList::create(Meal::class);
        foreach ($meals as $meal) {
            if ($meal->Foods()->count() == 0) {
                $mealswithoutfood->add($meal);
            }
        }

        //Filter mealswithoutfood for future date
        $mealswithoutfood = $mealswithoutfood->sort(['Parent.Date' => 'ASC', 'Time' => 'ASC']);
        $mealswithoutfood = GroupedList::create($mealswithoutfood);
        return $mealswithoutfood;
    }
}
