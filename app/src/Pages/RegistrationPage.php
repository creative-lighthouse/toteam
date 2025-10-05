<?php

namespace App\Pages;

use Page;

class RegistrationPage extends Page
{
    private static $table_name = 'RegistrationPage';

    private static $cms_icon = 'font-icon-p-user';

    private static $singular_name = "Registrierungsseite";
    private static $plural_name = "Registrierungsseiten";

    private static $allowed_children = 'none';
}
