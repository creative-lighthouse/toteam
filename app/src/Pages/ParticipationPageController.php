<?php

namespace App\Pages;

use Calendar;
use PageController;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Security;

class ParticipationPageController extends PageController
{
    private static $allowed_actions = [
        "add",
        "change",
        "delete",
    ];

    public function index()
    {
        //Check if there is a date param. Else use current date and redirect
        $date = $this->request->getVar('date');
        if (!$date) {
            $this->redirect($this->Link() . "?date=" . date('Y-m-d'));
        }

        return [
            'Calendar' => DBField::create_field('HTMLText', $this->RenderCalendar($date) ?? null),
            'Date' => $date,
        ];
    }

    public function RenderCalendar($date = null)
    {
        return new Calendar($date, Security::getCurrentUser());
    }
}
