<?php

namespace App\Admins;

use App\Maps\MapTilesSettings;
use SilverStripe\Admin\SingleRecordAdmin;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\Form;

/**
 * Class \App\Admins\MapTilesAdmin
 *
 * CMS-Bereich "Kartendaten": Stand der selbst gehosteten Karte, Einstellungen
 * und Aktualisierung per Knopfdruck (siehe MapTilesSettings).
 */
class MapTilesAdmin extends SingleRecordAdmin
{
    public const PERMISSION = 'CMS_ACCESS_MapTilesAdmin';

    private static string $url_segment = 'map-tiles';
    private static string $menu_title = 'Kartendaten';
    private static string $menu_icon = 'app/client/icons/totems/karten_totem_admin.png';
    // Wie die ModelAdmins, damit der Bereich alphabetisch einsortiert wird
    private static $menu_priority = -0.5;
    private static string $model_class = MapTilesSettings::class;

    private static array $required_permission_codes = [
        self::PERMISSION,
    ];

    private static $allowed_actions = [
        'doUpdateNow',
    ];

    public function doUpdateNow(array $data, Form $form): HTTPResponse
    {
        $this->save($data, $form);
        $message = MapTilesSettings::current()->queueUpdate();
        // Erst jetzt neu rendern, damit der Stand die eingeplante Aktualisierung zeigt
        $response = $this->getResponseNegotiator()->respond($this->getRequest());
        $response->addHeader('X-Status', rawurlencode($message));
        return $response;
    }
}
