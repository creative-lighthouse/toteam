<?php

namespace App\Tasks;

use SilverStripe\ORM\DataObject;

/**
 * Class \App\Tasks\TaskGroup
 *
 * @method \SilverStripe\ORM\ManyManyList|\App\Tasks\Task[] Tasks()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class TaskGroup extends DataObject
{
    private static $db = [
    ];
    
    private static $many_many = [
        'Tasks' => Task::class,
    ];

    private static $owns = [
    ];

    private static $field_labels = [
    ];

    private static $summary_fields = [
    ];

    private static $table_name = 'TaskGroup';
    private static $singular_name = "Aufgaben-Gruppe";
    private static $plural_name = "Aufgaben-Gruppe";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
