<?php

namespace App\Admins;

use App\Announcements\FeedPost;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\AnnouncementsAdmin
 *
 * CMS-Bereich "Feed": Beiträge des Mitteilungs-Totems ansehen und z.B. moderieren.
 */
class AnnouncementsAdmin extends ModelAdmin
{
    private static $menu_title = 'Feed';
    private static $url_segment = 'announcements';
    private static $menu_icon = 'app/client/icons/totems/nachrichten_totem_admin.png';

    private static $managed_models = [
        FeedPost::class,
    ];
}
