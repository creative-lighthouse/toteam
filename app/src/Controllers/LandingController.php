<?php

namespace App\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\Security;

/**
 * Serves the public marketing pages ("/", "/rechtliches").
 * These are a single Vue app with client-side routing (see landing.js),
 * so every one of these routes renders the same template.
 * Logged-in users visiting "/" are redirected straight to the Vue app dashboard.
 */
class LandingController extends Controller
{
    private static $allowed_actions = [
        'index',
    ];

    private static $url_handlers = [
        '' => 'index',
    ];

    public function index(HTTPRequest $request)
    {
        $loggedIn = (bool) Security::getCurrentUser();

        if ($request->getURL() === '' && $loggedIn) {
            return $this->redirect('/app/dashboard');
        }

        return $this->customise(['LoggedIn' => $loggedIn])->renderWith('Landing');
    }
}
