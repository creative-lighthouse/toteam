<?php

namespace App\Admins;

use App\Events\Event;
use App\Events\EventDayType;
use SilverStripe\Admin\ModelAdmin;

class EventAdmin extends ModelAdmin
{
    private static $menu_title = 'Events';

    private static $url_segment = 'person-directory';

    private static $managed_models = [
        Event::class,
        EventDayType::class,
    ];
}
