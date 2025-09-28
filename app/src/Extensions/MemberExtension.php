<?php

namespace App\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;
use App\HumanResources\Department;
use App\HumanResources\FoodPreferences;
use App\Tasks\Task;

class MemberExtension extends Extension
{
    private static $db = [
        "Nickname" => "Varchar(255)",
        "Joindate" => "Int",
    ];

    private static $has_one = [
        "Image" => Image::class,
    ];

    private static $has_many = [
        "FoodPreferences" => FoodPreferences::class,
    ];

    private static $belongs_many = [
        "Departments" => Department::class,
        "Tasks" => Task::class,
    ];

    private static $owns = [
        'Image',
    ];

    public function getParticipations() {}
}
