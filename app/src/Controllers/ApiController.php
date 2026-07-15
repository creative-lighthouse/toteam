<?php

namespace App\Controllers;

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
     * Check if user is authenticated
     */
    protected function requireAuth(): ?Member
    {
        $member = Security::getCurrentUser();
        
        if (!$member) {
            // Don't use httpError, return null instead
            return null;
        }
        
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
