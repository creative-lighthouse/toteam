<?php

namespace App\Controllers;

use App\Auth\JwtHelper;
use App\Teams\Organization;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;
use SilverStripe\Security\Member;

/**
 * Base API Controller with common functionality
 *
 */
class ApiController extends Controller
{
    private static $url_segment = 'api';
    
    private static $allowed_actions = [
        'index'
    ];
    
    /**
     * CORS and JSON response setup
     */
    public function init()
    {
        parent::init();
        
        // Set CORS headers for API access
        $this->getResponse()->addHeader('Content-Type', 'application/json');
        $this->getResponse()->addHeader('Access-Control-Allow-Origin', '*');
        $this->getResponse()->addHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->getResponse()->addHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
    
    /**
     * Resolves the current Member from the "Authorization: Bearer <JWT>" header.
     * Returns null (never throws/httpErrors) so callers can respond with their
     * own 401 payload.
     */
    protected function requireAuth(): ?Member
    {
        $header = $this->getRequest()->getHeader('Authorization') ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $memberID = JwtHelper::verifyAccessToken(trim($matches[1]));
        if (!$memberID) {
            return null;
        }

        $member = Member::get()->byID($memberID);
        if (!$member) {
            return null;
        }

        // Populated so existing framework/permission helpers (Permission::checkMember(),
        // canView()/canEdit() checks, etc.) that read the "current user" keep working
        // unchanged, regardless of how the member was actually resolved here.
        Security::setCurrentUser($member);

        return $member;
    }
    
    /**
     * Return JSON response
     */
    protected function jsonResponse($data, $statusCode = 200): HTTPResponse
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $response->setBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        
        return $response;
    }
    
    /**
     * Return error response
     */
    protected function errorResponse($message, $statusCode = 400): HTTPResponse
    {
        return $this->jsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
    
    /**
     * Return success response
     */
    protected function successResponse($data = [], $message = null): HTTPResponse
    {
        $response = [
            'success' => true,
            'data' => $data
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        return $this->jsonResponse($response);
    }
    
    /**
     * Ob der Nutzer die granulare Berechtigung $code in mindestens einer der
     * angegebenen Organisationen hat (Termine/Terminfindungen können mehreren Orgs zugeordnet sein).
     */
    protected function hasPermissionInAnyOrg(Member $member, array $orgIDs, string $code): bool
    {
        foreach ($orgIDs as $orgID) {
            $org = Organization::get()->byID($orgID);
            if ($org && $org->exists() && $member->hasOrgPermission($org, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get JSON body from request
     */
    protected function getJsonBody(): array
    {
        $body = $this->getRequest()->getBody();
        
        if (empty($body)) {
            return [];
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->httpError(400, 'Invalid JSON');
        }
        
        return $data ?? [];
    }
    
    public function index(HTTPRequest $request): HTTPResponse
    {
        return $this->jsonResponse([
            'name' => 'ToTeam API',
            'version' => '1.0',
            'endpoints' => [
                'auth' => '/api/v1/auth',
                'dashboard' => '/api/v1/dashboard',
                'announcements' => '/api/v1/announcements',
                'calendar' => '/api/v1/calendar',
                'food' => '/api/v1/food'
            ]
        ]);
    }
}
