<?php

namespace App\Tasks;

use App\Tasks\TaskGroup;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \App\Tasks\Task
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property int $OwnerID
 * @property int $ParentID
 * @method \SilverStripe\Security\Member Owner()
 * @method \App\Tasks\Task Parent()
 * @method \SilverStripe\ORM\ManyManyList|\App\Tasks\TaskGroup[] TaskGroups()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] Supporters()
 * @method \SilverStripe\ORM\ManyManyList|\App\Tasks\Task[] SubTasks()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
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
