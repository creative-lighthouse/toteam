<?php

namespace App\Pages;

use Page;

class CalendarPage extends Page
{
    private static $table_name = 'CalendarPage';

    private static $cms_icon = 'font-icon-p-user';

    private static $singular_name = "Kalenderseite";
    private static $plural_name = "Kalenderseiten";

    private static $allowed_children = 'none';
}
