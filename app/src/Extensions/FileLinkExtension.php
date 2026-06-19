<?php

namespace App\Extensions;

use SilverStripe\Core\Extension;

/**
 * Class \App\Extensions\FileLinkExtension
 *
 * @property \SilverStripe\LinkField\Models\FileLink|\App\Extensions\FileLinkExtension $owner
 */
class FileLinkExtension extends Extension
{
    private static array $owns = [
        'File',
    ];
}
