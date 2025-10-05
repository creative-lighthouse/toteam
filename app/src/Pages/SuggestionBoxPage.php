<?php

namespace App\Pages;

use Page;

class SuggestionBoxPage extends Page
{
    private static $table_name = 'SuggestionBoxPage';

    private static $singular_name = "Kummerkasten";
    private static $plural_name = "Kummerkästen";

    private static $cms_icon = 'font-icon-p-user';

    private static $allowed_children = 'none';
}
