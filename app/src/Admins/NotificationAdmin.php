<?php

namespace App\Admins;

use App\Notifications\SavedNotification;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\FoodAdmin
 *
 */
class NotificationsAdmin extends ModelAdmin
{
    private static $menu_title = 'Benachrichtigungen';

    private static $url_segment = 'notifications';
    private static $menu_icon_class = 'sp-icon-megaphone-admin-currentColor';

    private static $managed_models = [
        SavedNotification::class,
    ];
}
