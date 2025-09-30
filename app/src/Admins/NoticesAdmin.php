<?php

namespace App\Admins;

use App\Notices\Notice;
use App\Notices\NoticeCategory;
use SilverStripe\Admin\ModelAdmin;

class NoticesAdmin extends ModelAdmin
{
    private static $menu_title = 'Ankündigungen';

    private static $url_segment = 'notices-directory';

    private static $managed_models = [
        Notice::class,
        NoticeCategory::class,
    ];

    
}
