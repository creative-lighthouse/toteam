<?php

namespace App\Controllers;

use App\Notices\Notice;
use App\Notices\NoticeReadStatus;
use App\Controllers\BaseController;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

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

        //Mark notice as read for current user
        $currentUser = Security::getCurrentUser();
        if ($currentUser) {
            $readStatus = NoticeReadStatus::get()
                ->filter([
                    'ParentID' => $notice->ID,
                    'MemberID' => $currentUser->ID,
                ])->first();
            if (!$readStatus) {
                $readStatus = NoticeReadStatus::create();
                $readStatus->ParentID = $notice->ID;
                $readStatus->MemberID = $currentUser->ID;
            }
            $readStatus->DateTime = date('Y-m-d H:i:s');
            $readStatus->write();
        }

        return [
            'Notice' => $notice,
        ];
    }

    public static function getUnreadNotices($memberID)
    {
        $allNotices = Notice::get();

        //Filter notices based on release and expiry date
        $currentDate = date('Y-m-d H:i:s');
        $allNotices = $allNotices->filterAny([
            'ReleaseDate:LessThanOrEqual' => $currentDate,
            'ReleaseDate' => null,
        ])->filterAny([
            'ExpiryDate:GreaterThanOrEqual' => $currentDate,
            'ExpiryDate' => null,
        ]);

        $readNoticeIDs = NoticeReadStatus::get()
            ->filter('MemberID', $memberID)
            ->column('ParentID');

        if (!empty($readNoticeIDs)) {
            $unreadNotices = $allNotices->exclude('ID', $readNoticeIDs);
        } else {
            $unreadNotices = $allNotices;
        }

        return $unreadNotices;
    }
}
