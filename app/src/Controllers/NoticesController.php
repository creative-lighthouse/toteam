<?php

namespace App\Controllers;

use App\Notices\Notice;
use App\Controllers\BaseController;

/**
 * Class \App\Controllers\NoticesController
 *
 */
class NoticesController extends BaseController
{
    private static $url_segment = 'notices';

    private static $allowed_actions = [
        "view",
    ];

    public function index()
    {
        //Check if there is a date param. Else use current date and redirect
        $notices = Notice::get()->sort('Created', 'DESC');

        //Filter notices based on release and expiry date
        $currentDate = date('Y-m-d H:i:s');
        $notices = $notices->filterAny([
            'ReleaseDate:LessThanOrEqual' => $currentDate,
            'ReleaseDate' => null,
        ])->filterAny([
            'ExpiryDate:GreaterThanOrEqual' => $currentDate,
            'ExpiryDate' => null,
        ]);

        return $this->render([
            'Notices' => $notices,
        ]);
    }

    public function view($request)
    {
        $noticeID = $request->param('ID');
        $notice = Notice::get_by_id($noticeID);
        if (!$notice) {
            return $this->httpError(404, 'Ankündigung nicht gefunden');
        }

        return [
            'Notice' => $notice,
        ];
    }
}
