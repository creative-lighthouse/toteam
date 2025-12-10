<?php

namespace App\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Security\Security;

/**
 * Class \App\Controllers\NotificationsController
 *
 */
class NotificationsController extends BaseController
{
    private static $url_segment = 'notifications';

    private static $allowed_actions = [
        'index'
    ];

    public function index()
    {
        if (!Security::getCurrentUser()) {
            return $this->redirect('/Security/login');
        }

        return $this->renderWith(['NotificationsController', 'Page']);
    }
}
