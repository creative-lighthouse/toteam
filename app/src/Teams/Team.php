<?php

namespace App\Teams;

use App\Tasks\TaskGroup;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class Team extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "AllowsSelfJoining" => "Boolean",
    ];

    private static $has_many = [
        "Projects" => Project::class,
    ];

    private static $has_one = [
    ];

    private static $many_many = [
        "Members" => Member::class,
    ];

    private static $owns = [
        'Members'
    ];

    private static $field_labels = [

    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'Team';
    private static $singular_name = "Team";
    private static $plural_name = "Teams";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
