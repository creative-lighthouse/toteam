<?php

namespace App\Notifications;

use SilverStripe\ORM\DataObject;

/**
 * Class \App\Notifications\PendingNotificationJob
 *
 * @property ?string $SourceClass
 * @property int $SourceID
 * @property ?string $EventType
 * @property ?string $Status
 * @property ?string $ErrorMessage
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class PendingNotificationJob extends DataObject
{
    private static $db = [
        'SourceClass'  => 'Varchar(255)',
        'SourceID'     => 'Int',
        'EventType'    => 'Varchar(100)',
        'Status'       => "Enum('pending,done,failed','pending')",
        'ErrorMessage' => 'Text',
    ];

    private static $table_name = 'PendingNotificationJob';
    private static $default_sort = 'Created ASC';
}
