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
        'index'
    ];
    
    private static $url_handlers = [
        '$Action/$ID/$OtherID' => 'index'
    ];
    
    /**
     * Serve the Vue app - Vue Router handles all client-side routing
     * This catches all URLs under /app/* and serves the same SPA
     */
    public function index(HTTPRequest $request)
    {
        // Template loads Vite assets directly
        return $this->renderWith('VueApp');
    }
}
