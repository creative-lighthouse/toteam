<?php

namespace App\Admins;

use App\Maps\Map;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\NoticesAdmin
 *
 */
class MapAdmin extends ModelAdmin
{
    private static $menu_title = 'Lagepläne';

    private static $url_segment = 'maps';
    private static $menu_icon = 'app/client/icons/totems/karten_totem_admin.png';

    private static $managed_models = [
        Map::class,
    ];
}
