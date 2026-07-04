<?php

namespace App\Admins;

use App\Money\MoneyAccount;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\TaskAdmin
 *
 */
class MoneyAdmin extends ModelAdmin
{
    private static $menu_title = 'Money';

    private static $url_segment = 'money-directory';
    private static $menu_icon = 'app/client/icons/totems/geld_totem_admin.png';

    private static $managed_models = [
        MoneyAccount::class,
    ];
}
