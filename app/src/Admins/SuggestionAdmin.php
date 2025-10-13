<?php

namespace App\Admins;

use App\SuggestionBox\Suggestion;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\SuggestionAdmin
 *
 */
class SuggestionAdmin extends ModelAdmin
{
    private static $menu_title = 'Kummerkasten';

    private static $url_segment = 'suggestion-directory';

    private static $managed_models = [
        Suggestion::class,
    ];
}
