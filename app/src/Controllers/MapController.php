<?php

namespace App\Controllers;

use App\Maps\Map;
use App\Maps\MapLayer;
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
        "layeredit",
        "savelayer",
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

        // Prepare layers data as JSON
        $layersData = [];
        foreach ($map->MapLayers() as $layer) {
            $poisData = [];
            foreach ($layer->POIs() as $poi) {
                $poisData[] = [
                    'id' => $poi->ID,
                    'title' => $poi->Title,
                    'description' => $poi->Description,
                    'active' => (bool)$poi->Active,
                    'position' => $poi->Coordinates,
                    'markerColor' => $poi->getMarkerColor(),
                    'markerText' => $poi->getMarkerText(),
                ];
            }

            $layersData[] = [
                'id' => $layer->ID,
                'title' => $layer->Title,
                'active' => (bool)$layer->Active,
                'imageUrl' => $layer->Image()->exists() ? $layer->Image()->URL : '',
                'coordinatesUL' => $layer->getCoordinatesUL(),
                'coordinatesLR' => $layer->getCoordinatesLR(),
                'layerColor' => $layer->LayerColor ?: '#999999',
                'pois' => $poisData
            ];
        }

        return [
            'Map' => $map,
            'LayersJSON' => json_encode($layersData)
        ];
    }

    public function getActiveMaps ()
    {
        $maps = Map::get()->filter('Active', true)->sort('Created', 'DESC');

        // Filter by user's organizations
        $maps = $this->filterByUserOrganizations($maps);

        return $maps;
    }

    public function layeredit($request)
    {
        //Action for editing layers via frontend
        $layerID = $request->param('ID');
        $layer = MapLayer::get()->byID($layerID);

        if (!$layer || !$layer->exists()) {
            return $this->httpError(404, 'Ebene nicht gefunden');
        }

        $map = $layer->Parent();

        //Check if user has access to this map
        $organizationIDs = $this->getUserOrganizationIDs();
        if (!in_array($map->ParentID, $organizationIDs)) {
            return $this->httpError(403, 'Zugriff verweigert');
        }

        // Prepare only the current layer data as JSON
        $poisData = [];
        foreach ($layer->POIs() as $poi) {
            $poisData[] = [
                'id' => $poi->ID,
                'title' => $poi->Title,
                'description' => $poi->Description,
                'active' => (bool)$poi->Active,
                'position' => $poi->Coordinates,
                'markerColor' => $poi->getMarkerColor(),
                'markerText' => $poi->getMarkerText(),
            ];
        }

        $layerData = [
            'id' => $layer->ID,
            'title' => $layer->Title,
            'active' => true, // Always show the layer being edited
            'imageUrl' => $layer->Image()->exists() ? $layer->Image()->URL : '',
            'coordinatesUL' => $layer->getCoordinatesUL(),
            'coordinatesLR' => $layer->getCoordinatesLR(),
            'layerColor' => $layer->LayerColor ?: '#999999',
            'pois' => $poisData
        ];

        return $this->render([
            'Layer' => $layer,
            'Map' => $map,
            'LayerJSON' => json_encode($layerData)
        ]);
    }

    public function savelayer($request)
    {
        // Handle AJAX request to save layer data
        if (!$request->isPOST()) {
            $this->getResponse()->setStatusCode(400);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode(['success' => false, 'error' => 'Ungültige Anfrage-Methode']);
        }

        $layerID = $request->param('ID');
        if (!$layerID) {
            $this->getResponse()->setStatusCode(400);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode(['success' => false, 'error' => 'Keine Layer-ID angegeben']);
        }

        $layer = MapLayer::get()->byID($layerID);

        if (!$layer || !$layer->exists()) {
            $this->getResponse()->setStatusCode(404);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode(['success' => false, 'error' => 'Ebene nicht gefunden']);
        }

        $map = $layer->Parent();

        // Check if user has access
        $organizationIDs = $this->getUserOrganizationIDs();
        if (!in_array($map->ParentID, $organizationIDs)) {
            $this->getResponse()->setStatusCode(403);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode(['success' => false, 'error' => 'Zugriff verweigert']);
        }

        // Get JSON data from request
        $jsonData = $request->getBody();
        $data = json_decode($jsonData, true);

        if (!$data) {
            $this->getResponse()->setStatusCode(400);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode(['success' => false, 'error' => 'Ungültige Daten']);
        }

        // Store the layer data as JSON
        // For now, we'll just log it - in next steps we can update POIs, image, etc.
        error_log('Saving layer data: ' . print_r($data, true));

        // TODO: In next steps, update layer image, POIs, etc. based on $data

        $this->getResponse()->setStatusCode(200);
        $this->getResponse()->addHeader('Content-Type', 'application/json');
        return json_encode([
            'success' => true,
            'message' => 'Änderungen gespeichert'
        ]);
    }
}
