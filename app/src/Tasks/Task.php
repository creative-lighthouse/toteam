<?php

namespace App\Tasks;

use App\Tasks\TaskGroup;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class Task extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
    ];

    private static $has_one = [
        "Owner" => Member::class,
        "Parent" => Task::class,
    ];

    private static $many_many = [
        'TaskGroups' => TaskGroup::class,
        'Supporters' => Member::class,
        'SubTasks' => Task::class,
    ];

    private static $owns = [
        'SubTasks'
    ];

    private static $field_labels = [

    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'Task';
    private static $singular_name = "Aufgabe";
    private static $plural_name = "Aufgaben";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
