<?php

namespace App\Pages;

use Page;

class ICSPage extends Page
{
    private static $table_name = 'ICSPage';

    private static $cms_icon = 'font-icon-p-user';

    private static $singular_name = "ICS-Seite";
    private static $plural_name = "ICS-Seiten";

    private static $allowed_children = 'none';
}
