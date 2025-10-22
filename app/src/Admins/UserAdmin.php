<?php

namespace App\Admins;

use App\HumanResources\Department;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\Member;

/**
 * Class \App\Admins\UserAdmin
 *
 */
class UserAdmin extends ModelAdmin
{
    private static $menu_title = 'Team';

    private static $url_segment = 'user-directory';
    private static $menu_icon = 'app/client/icons/totems/team_totem_admin.png';


    private static $managed_models = [
        Member::class,
        Department::class,
    ];
}
