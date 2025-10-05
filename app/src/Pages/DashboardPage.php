<?php

namespace App\Pages;

use Page;

class DashboardPage extends Page
{
    private static $table_name = 'DashboardPage';

    private static $cms_icon = 'font-icon-p-user';

    private static $singular_name = "Dashboardseite";
    private static $plural_name = "Dashboardseiten";

    private static $allowed_children = 'none';
}
