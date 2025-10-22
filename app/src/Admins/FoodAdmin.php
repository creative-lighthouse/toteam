<?php

namespace App\Admins;

use App\Food\Food;
use App\Food\Meal;
use App\HumanResources\Allergy;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\FoodAdmin
 *
 */
class FoodAdmin extends ModelAdmin
{
    private static $menu_title = 'Food';

    private static $url_segment = 'food-directory';
    private static $menu_icon = 'app/client/icons/totems/essen_totem_admin.png';

    private static $managed_models = [
        Food::class,
        Meal::class,
        Allergy::class,
    ];
}
