<?php

namespace App\Pages;

use Calendar;
use PageController;
use SilverStripe\Security\Security;

class ParticipationPageController extends PageController
{
    private static $allowed_actions = [
        "add",
        "change",
        "delete",
    ];

    public function getCalendar()
    {
        return new Calendar(null, Security::getCurrentUser()->ID);
    }
}
