<?php

namespace App\Pages;

use PageController;
use App\Notices\Notice;

class NoticesPageController extends PageController
{
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

        return [
            'Notices' => $notices,
        ];
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
