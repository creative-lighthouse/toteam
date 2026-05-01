<?php

namespace App\Controllers;

use App\Notices\Notice;
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

        // Filter by user's organizations
        $notices = $this->filterByUserOrganizations($notices);

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

    public static function getUnreadNotices($memberID)
    {
        $member = \SilverStripe\Security\Member::get()->byID($memberID);
        if (!$member) {
            return Notice::get()->filter('ID', 0);
        }

        $organizationIDs = $member->getOrganizationIDs();

        if (empty($organizationIDs)) {
            return Notice::get()->filter('ID', 0);
        }

        $currentDate = date('Y-m-d H:i:s');
        $notices = Notice::get()
            ->filter(['Organisations.ID' => $organizationIDs])
            ->distinct(true)
            ->filterAny([
                'ReleaseDate:LessThanOrEqual' => $currentDate,
                'ReleaseDate' => null,
            ])->filterAny([
                'ExpiryDate:GreaterThanOrEqual' => $currentDate,
                'ExpiryDate' => null,
            ]);

        return $notices;
    }
}
