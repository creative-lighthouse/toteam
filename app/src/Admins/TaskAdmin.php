<?php

namespace App\Admins;

use App\Tasks\Task;
use App\Tasks\TaskGroup;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\TaskAdmin
 *
 */
class TaskAdmin extends ModelAdmin
{
    private static $menu_title = 'Tasks';

    private static $url_segment = 'tasks-directory';
    private static $menu_icon = 'app/client/icons/totems/todos_totem_admin.png';

    private static $managed_models = [
        Task::class,
        TaskGroup::class,
    ];
}
