<?php

namespace App\Pages;

use Page;

class ProfilePage extends Page
{
    private static $table_name = 'ProfilePage';

    private static $cms_icon = 'font-icon-p-user';

    private static $singular_name = "Profilseite";
    private static $plural_name = "Profilseiten";

    private static $allowed_children = 'none';
}
