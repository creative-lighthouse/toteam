<?php

namespace App\Admins;

use App\Marketing\PosterDistribution;
use App\Marketing\PosterSize;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\MarketingAdmin
 *
 */
class MarketingAdmin extends ModelAdmin
{
    private static $menu_title = 'Marketing';

    private static $url_segment = 'marketing-directory';
    private static $menu_icon = 'app/client/icons/totems/marketing_totem_admin.png';

    private static $managed_models = [
        PosterDistribution::class,
        PosterSize::class,
    ];
}
