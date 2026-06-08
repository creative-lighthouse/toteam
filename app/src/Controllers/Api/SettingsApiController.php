<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Class \App\Controllers\Api\SettingsApiController
 *
 */
class SettingsApiController extends ApiController
{
    private static $url_segment = 'api/v1/settings';

    private static $allowed_actions = [
        'index',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($request->httpMethod() === 'GET') {
            return $this->jsonResponse([
                'NotifyEvents'        => (bool) $member->NotifyEvents,
                'NotifyAnnouncements' => (bool) $member->NotifyAnnouncements,
                'NotifyMeals'         => (bool) $member->NotifyMeals,
                'NotifyMaps'          => (bool) $member->NotifyMaps,
                'NotifyApplications'  => (bool) $member->NotifyApplications,
            ]);
        }

        if ($request->httpMethod() === 'PATCH' || $request->httpMethod() === 'POST') {
            $data = $this->getJsonBody();

            $allowed = ['NotifyEvents', 'NotifyAnnouncements', 'NotifyMeals', 'NotifyMaps', 'NotifyApplications'];
            $changed = false;

            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $member->$field = (bool) $data[$field];
                    $changed = true;
                }
            }

            if ($changed) {
                $member->write();
            }

            return $this->successResponse([], 'Einstellungen gespeichert');
        }

        return $this->errorResponse('Method not allowed', 405);
    }
}
