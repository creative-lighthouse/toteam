<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 * Class \App\Controllers\FoodController
 *
 */
class FoodController extends BaseController
{
    private static $url_segment = 'food';

    private static $allowed_actions = [
    ];

    public function index()
    {


        return $this->render([
        ]);
    }
}
