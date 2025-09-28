<?php

namespace App\Admins;

use App\Tasks\Task;
use App\Tasks\TaskGroup;
use SilverStripe\Admin\ModelAdmin;

class TaskAdmin extends ModelAdmin
{
    private static $menu_title = 'Tasks';

    private static $url_segment = 'tasks-directory';

    private static $managed_models = [
        Task::class,
        TaskGroup::class,
    ];
}
