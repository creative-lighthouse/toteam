<?php

namespace App\Admins;

use App\Feedback\Feedback;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\FeedbackAdmin
 *
 */
class FeedbackAdmin extends ModelAdmin
{
    private static $menu_title = 'Feedback';

    private static $url_segment = 'feedback-directory';

    private static $menu_icon = 'app/client/icons/feedback_admin.svg';

    private static $managed_models = [
        Feedback::class,
    ];
}
