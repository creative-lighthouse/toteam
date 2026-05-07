<?php

namespace App\Admins;

use App\Announcements\Announcement;
use App\Announcements\AnnouncementCategory;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\AnnouncementsAdmin
 *
 */
class AnnouncementsAdmin extends ModelAdmin
{
    private static $menu_title = 'Ankündigungen';
    private static $url_segment = 'announcements';
    private static $menu_icon = 'app/client/icons/totems/nachrichten_totem_admin.png';

    private static $managed_models = [
        Announcement::class,
        AnnouncementCategory::class,
    ];
}
