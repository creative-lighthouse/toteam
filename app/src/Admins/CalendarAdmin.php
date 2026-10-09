<?php

namespace App\Admins;

use App\Calendar\Absence;
use App\Calendar\Appointment;
use App\Calendar\AppointmentType;
use App\Teams\OrgEventAgeGroup;
use App\Teams\OrgEventType;
use SilverStripe\Admin\ModelAdmin;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class \App\Admins\CalendarAdmin
 *
 */
class CalendarAdmin extends ModelAdmin
{
    private static $menu_title = 'Kalender';

    private static $url_segment = 'calendar';
    private static $menu_icon = 'app/client/icons/totems/kalender_totem_admin.png';

    private static $managed_models = [
        Appointment::class,
        AppointmentType::class,
        Absence::class,
        OrgEventType::class,
        OrgEventAgeGroup::class,
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        // Event-Arten und Altersgruppen erscheinen im Event-Formular in dieser Reihenfolge
        foreach ([OrgEventType::class, OrgEventAgeGroup::class] as $class) {
            $grid = $form->Fields()->dataFieldByName($this->sanitiseClassName($class));
            if ($grid) {
                $grid->getConfig()->addComponent(GridFieldOrderableRows::create('SortOrder'));
            }
        }
        return $form;
    }
}
