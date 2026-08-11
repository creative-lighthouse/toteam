<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Feedback\Feedback;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Class \App\Controllers\Api\FeedbackApiController
 *
 */
class FeedbackApiController extends ApiController
{
    private static $url_segment = 'api/v1/feedback';

    private static $allowed_actions = [
        'submit',
    ];

    private const VALID_TYPES = ['BugReport', 'FeatureRequest'];

    public function submit(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        if (!$member) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if (!$request->isPOST()) {
            return $this->errorResponse('Method not allowed', 405);
        }

        $body = json_decode($request->getBody(), true) ?: [];

        $title = trim($body['Title'] ?? '');
        $description = trim($body['Description'] ?? '');

        if ($title === '' || $description === '') {
            return $this->errorResponse('Titel und Beschreibung sind erforderlich', 400);
        }

        $type = $body['Type'] ?? '';
        if (!in_array($type, self::VALID_TYPES, true)) {
            $type = 'BugReport';
        }

        $feedback = Feedback::create();
        $feedback->Title = $title;
        $feedback->Description = $description;
        $feedback->Type = $type;
        $feedback->URL = trim($body['URL'] ?? '');
        $feedback->NotifyByEmail = !empty($body['NotifyByEmail']);
        $feedback->SubmitterID = $member->ID;
        $feedback->write();

        return $this->successResponse([
            'ID'            => $feedback->ID,
            'Title'         => $feedback->Title,
            'Description'   => $feedback->Description,
            'Type'          => $feedback->Type,
            'Status'        => $feedback->Status,
            'URL'           => $feedback->URL,
            'NotifyByEmail' => (bool) $feedback->NotifyByEmail,
        ], null);
    }
}
