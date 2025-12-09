<?php

namespace App\Admins;

use App\HumanResources\Department;
use App\Teams\Organization;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\Member;

/**
 * Class \App\Admins\UserAdmin
 *
 */
class TeamAdmin extends ModelAdmin
{
    private static $menu_title = 'Team';

    private static $url_segment = 'team-directory';
    private static $menu_icon = 'app/client/icons/totems/team_totem_admin.png';


    private static $managed_models = [
        Member::class,
        Organization::class,
    ];
}
