<?php

namespace App\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\View\Requirements;
use Atwx\ViteHelper\Helper\ViteHelper;

/**
 * Vue App Controller - Serves the Vue SPA
 *
 */
class VueAppController extends Controller
{
    private static $url_segment = 'app';
    
    private static $allowed_actions = [
        'index',
        'login'
    ];
    
    /**
     * Serve the Vue app
     */
    public function index(HTTPRequest $request)
    {
        // Load Vue app assets
        Requirements::javascript(ViteHelper::Vite('app/client/src/vue/app.js'));
        
        // Return template
        return $this->renderWith('VueApp');
    }

    public function login(HTTPRequest $request)
    {
        // Load Vue app assets
        Requirements::javascript(ViteHelper::Vite('app/client/src/vue/app.js'));
        
        // Return template
        return $this->renderWith('VueApp');
    }
}
