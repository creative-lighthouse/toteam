<?php

namespace App\Admins;

use App\Calendar\Absence;
use App\Calendar\Appointment;
use App\Calendar\AppointmentType;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\CalendarAdmin
 *
 */
class CalendarAdmin extends ModelAdmin
{
    private static $menu_title = 'Kalender';

    private static $url_segment = 'calendar';
    private static $menu_icon = 'app/client/icons/totems/kalender_totem_admin.png';

    private static $managed_models = [
        Appointment::class,
        AppointmentType::class,
        Absence::class,
    ];
}
