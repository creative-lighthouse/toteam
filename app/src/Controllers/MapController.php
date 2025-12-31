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
        "uploadlayerimage",
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

        try {
            // Update layer properties if provided
            if (isset($data['title'])) {
                $layer->Title = $data['title'];
            }

            if (isset($data['description'])) {
                $layer->Description = $data['description'];
            }

            if (isset($data['layerColor'])) {
                $layer->LayerColor = $data['layerColor'];
            }

            // Handle POIs updates
            if (isset($data['pois']) && is_array($data['pois'])) {
                // Get list of POI IDs from the request
                $sentPoiIds = [];

                foreach ($data['pois'] as $poiData) {
                    if (!isset($poiData['id'])) {
                        continue;
                    }

                    // Check if this is a new POI (temporary ID or marked as new)
                    $isNew = isset($poiData['isNew']) && $poiData['isNew'];

                    if ($isNew) {
                        // Create new POI
                        $poi = \App\Maps\MapPOI::create();
                        $poi->ParentID = $layer->ID;
                        $poi->Active = true;
                    } else {
                        // Track existing POI IDs
                        $sentPoiIds[] = $poiData['id'];

                        // Update existing POI
                        $poi = \App\Maps\MapPOI::get()->byID($poiData['id']);

                        // Verify POI belongs to this layer
                        if (!$poi || $poi->ParentID != $layer->ID) {
                            continue;
                        }
                    }

                    // Update POI properties
                    if (isset($poiData['title'])) {
                        $poi->Title = $poiData['title'];
                    }

                    if (isset($poiData['description'])) {
                        $poi->Description = $poiData['description'];
                    }

                    if (isset($poiData['position'])) {
                        $poi->Coordinates = $poiData['position'];
                    }

                    if (isset($poiData['markerColor'])) {
                        $poi->MarkerColor = $poiData['markerColor'];
                    }

                    if (isset($poiData['markerText'])) {
                        $poi->MarkerText = $poiData['markerText'];
                    }

                    if (isset($poiData['active'])) {
                        $poi->Active = (bool)$poiData['active'];
                    }

                    $poi->write();
                }

                // Delete POIs that are no longer in the list
                $existingPOIs = $layer->POIs();
                foreach ($existingPOIs as $existingPOI) {
                    if (!in_array($existingPOI->ID, $sentPoiIds)) {
                        // This POI was not in the sent list, delete it
                        $existingPOI->delete();
                    }
                }
            }

            // Save layer
            $layer->write();

            $this->getResponse()->setStatusCode(200);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode([
                'success' => true,
                'message' => 'Änderungen gespeichert'
            ]);

        } catch (\Exception $e) {
            error_log('Error saving layer: ' . $e->getMessage());
            $this->getResponse()->setStatusCode(500);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode([
                'success' => false,
                'error' => 'Fehler beim Speichern: ' . $e->getMessage()
            ]);
        }
    }

    public function uploadlayerimage($request)
    {
        try {
            $layerID = $request->param('ID');
            $layer = MapLayer::get()->byID($layerID);

            if (!$layer) {
                throw new \Exception('Ebene nicht gefunden');
            }

            // Check access permissions
            $map = Map::get()->byID($layer->ParentID);
            $organizationIDs = $this->getUserOrganizationIDs();
            if (!in_array($map->ParentID, $organizationIDs)) {
                throw new \Exception('Zugriff verweigert');
            }

            // Check if file was uploaded
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Keine Datei hochgeladen oder Upload-Fehler');
            }

            // Create upload validator for SilverStripe 6
            $validator = new \SilverStripe\Assets\Upload_Validator();
            $validator->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $validator->setAllowedMaxFileSize(10 * 1024 * 1024); // 10MB

            // Create upload handler
            $upload = new \SilverStripe\Assets\Upload();
            $upload->setValidator($validator);

            // Create new file object
            $file = new \SilverStripe\Assets\Image();

            // Perform the upload
            $result = $upload->loadIntoFile($_FILES['image'], $file, 'MapLayers');

            if (!$result) {
                $errors = $upload->getErrors();
                throw new \Exception('Fehler beim Hochladen: ' . implode(', ', $errors));
            }

            // Write file to get ID
            $file->write();
            $file->publishSingle();

            // Attach image to layer
            $layer->ImageID = $file->ID;
            $layer->write();

            $this->getResponse()->setStatusCode(200);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode([
                'success' => true,
                'message' => 'Bild erfolgreich hochgeladen',
                'imageUrl' => $file->getAbsoluteURL()
            ]);

        } catch (\Exception $e) {
            error_log('Error uploading layer image: ' . $e->getMessage());
            error_log('Upload data: ' . print_r($_FILES, true));
            $this->getResponse()->setStatusCode(500);
            $this->getResponse()->addHeader('Content-Type', 'application/json');
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate contrast color (black or white) for a given background color
     * @param string $bgColor Hex color code (e.g., #FF5733)
     * @return string 'black' or 'white'
     */
    public function getContrastColorForPOI($bgColor)
    {
        // Remove # if present
        $bgColor = str_replace('#', '', $bgColor);

        // Parse RGB
        $r = hexdec(substr($bgColor, 0, 2));
        $g = hexdec(substr($bgColor, 2, 2));
        $b = hexdec(substr($bgColor, 4, 2));

        // Calculate relative luminance using ITU-R BT.709
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Return black for light backgrounds, white for dark backgrounds
        return $luminance > 0.5 ? 'black' : 'white';
    }
}
