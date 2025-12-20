<?php

namespace App\Controllers;

use App\Maps\Map;
use App\Controllers\BaseController;

/**
 * Class \App\Controllers\NoticesController
 *
 */
class MapController extends BaseController
{
    private static $url_segment = 'maps';

    private static $allowed_actions = [
        "view",
    ];

    public function index()
    {
        $maps = Map::get()->sort('Created', 'DESC');

        // Filter by user's organizations
        $maps = $this->filterByUserOrganizations($maps);

        return $this->render([
            'Maps' => $maps,
        ]);
    }

    public function view($request)
    {
        $mapID = $request->param('ID');
        $map = Map::get()->byID($mapID);

        //Check if user has access to this map
        $organizationIDs = $this->getUserOrganizationIDs();
        if (!in_array($map->ParentID, $organizationIDs)) {
            return $this->httpError(403, 'Zugriff verweigert');
        }

        return [
            'Map' => $map,
        ];
    }

    public function getActiveMaps ()
    {
        $maps = Map::get()->filter('Active', true)->sort('Created', 'DESC');

        // Filter by user's organizations
        $maps = $this->filterByUserOrganizations($maps);

        return $maps;
    }
}
