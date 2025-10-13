<?php

namespace App\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\View\SSViewer;

/**
 * Class \App\Controllers\BaseController
 *
 */
class BaseController extends Controller
{
    public function getViewer($action)
    {
        // Hard-coded templates
        if (isset($this->templates[$action]) && $this->templates[$action]) {
            $templates = $this->templates[$action];
        } elseif (isset($this->templates['index']) && $this->templates['index']) {
            $templates = $this->templates['index'];
        } elseif ($this->template) {
            $templates = $this->template;
        } else {
            // Build templates based on class hierarchy
            $actionTemplates = [];
            $classTemplates = [];
            $parentClass = static::class;
            while ($parentClass !== parent::class) {
                // _action templates have higher priority
                if ($action && $action != 'index') {
                    $actionTemplates[] = strtok($parentClass ?? '', '_') . '_' . $action;
                }
                // class templates have lower priority
                $classTemplates[] = strtok($parentClass ?? '', '_');
                $parentClass = get_parent_class($parentClass ?? '');
            }

            // Add controller templates for inheritance chain
            $templates = array_unique(array_merge($actionTemplates, $classTemplates));
        }

        // Add page
        $templates[] = 'Page';
        return SSViewer::create($templates);
    }

    public function IsCurrentRoute($route)
    {
        $currentURL = $this->getRequest()->getURL();
        //check if current URL includes the route
        return str_contains($currentURL, $route);
    }
}
