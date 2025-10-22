<?php

namespace App\Admins;

use App\Events\Event;
use App\Events\EventDayType;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\EventAdmin
 *
 */
class EventAdmin extends ModelAdmin
{
    private static $menu_title = 'Events';

    private static $url_segment = 'event-directory';
    private static $menu_icon = 'app/client/icons/totems/kalender_totem_admin.png';

    private static $managed_models = [
        Event::class,
        EventDayType::class,
    ];
}
