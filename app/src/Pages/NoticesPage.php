<?php

namespace App\Pages;

use Page;

class NoticesPage extends Page
{
    private static $table_name = 'NoticesPage';

    private static $cms_icon = 'font-icon-p-user';

    private static $singular_name = "Ankündigungsseite";
    private static $plural_name = "Ankündigungsseiten";

    private static $allowed_children = 'none';
}
