<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use SilverStripe\Security\Security;

/**
 * Class \App\Controllers\LinksController
 *
 */
class LinksController extends BaseController
{
    private static $url_segment = 'links';

    private static $allowed_actions = [
    ];

    public function index()
    {
        $currentuser = Security::getCurrentUser();

        if (!$currentuser) {
            return $this->redirect('registration');
        }

        return $this->render([
            'User' => $currentuser,
        ]);
    }
}
