<?php

namespace App\Teams;

use App\Tasks\TaskGroup;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class Project extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Description" => "Text",
        "AllowsSelfJoining" => "Boolean",
    ];

    private static $has_one = [
        "Parent" => Team::class,
        "ParentProject" => Project::class,
    ];

    private static $many_many = [
        "Heads" => Member::class,
        "Members" => Member::class,
        "SubProjects" => Project::class,
    ];

    private static $owns = [
        "SubProjects",
    ];

    private static $field_labels = [

    ];

    private static $summary_fields = [
        "Title" => "Titel",
    ];

    private static $table_name = 'Project';
    private static $singular_name = "Projekt";
    private static $plural_name = "Projekte";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
