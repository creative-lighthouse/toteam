<?php

namespace App\Controllers;

use App\Announcements\Announcement;
use App\Controllers\BaseController;

/**
 * Class \App\Controllers\AnnouncementsController
 *
 */
class AnnouncementsController extends BaseController
{
    private static $url_segment = 'announcements';

    private static $allowed_actions = [
        "view",
    ];

    public function index()
    {
        $announcements = Announcement::get()->sort('Created', 'DESC');
        $announcements = $this->filterByUserOrganizations($announcements);

        $currentDate = date('Y-m-d H:i:s');
        $announcements = $announcements->filterAny([
            'ReleaseDate:LessThanOrEqual' => $currentDate,
            'ReleaseDate' => null,
        ])->filterAny([
            'ExpiryDate:GreaterThanOrEqual' => $currentDate,
            'ExpiryDate' => null,
        ]);

        return $this->render([
            'Announcements' => $announcements,
        ]);
    }

    public function view($request)
    {
        $announcementID = $request->param('ID');
        $announcement = Announcement::get_by_id($announcementID);
        if (!$announcement) {
            return $this->httpError(404, 'Ankündigung nicht gefunden');
        }

        return [
            'Announcement' => $announcement,
        ];
    }

    public static function getUnreadAnnouncements($memberID)
    {
        $member = \SilverStripe\Security\Member::get()->byID($memberID);
        if (!$member) {
            return Announcement::get()->filter('ID', 0);
        }

        $organizationIDs = $member->getOrganizationIDs();

        if (empty($organizationIDs)) {
            return Announcement::get()->filter('ID', 0);
        }

        $currentDate = date('Y-m-d H:i:s');
        return Announcement::get()
            ->filter(['Organisations.ID' => $organizationIDs])
            ->distinct(true)
            ->filterAny([
                'ReleaseDate:LessThanOrEqual' => $currentDate,
                'ReleaseDate' => null,
            ])->filterAny([
                'ExpiryDate:GreaterThanOrEqual' => $currentDate,
                'ExpiryDate' => null,
            ]);
    }
}
