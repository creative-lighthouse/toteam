<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 * Class \App\Controllers\NotFoundController
 *
 */
class NotFoundController extends BaseController
{
    private static $url_segment = 'notfound';

    private static $allowed_actions = [
    ];

    public function index()
    {
        $this->getResponse()->setStatusCode(404);
        return $this->render();
    }
}
