<?php

namespace App\Admins;

use App\Food\Food;
use App\HumanResources\Allergy;
use SilverStripe\Admin\ModelAdmin;

class FoodAdmin extends ModelAdmin
{
    private static $menu_title = 'Food';

    private static $url_segment = 'food-directory';

    private static $managed_models = [
        Food::class,
        Allergy::class,
    ];
}
