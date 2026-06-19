<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\Member;

/**
 * Authentication API Controller
 *
 */
class AuthApiController extends ApiController
{
    private static $url_segment = 'api/v1/auth';
    
    private static $allowed_actions = [
        'check',
        'login',
        'logout'
    ];
    
    /**
     * Check if user is authenticated
     */
    public function check(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        
        if ($member) {
            return $this->jsonResponse([
                'authenticated' => true,
                'user' => $this->serializeMember($member)
            ]);
        }
        
        return $this->jsonResponse([
            'authenticated' => false,
            'user' => null
        ]);
    }
    
    /**
     * Login endpoint
     */
    public function login(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $data = $this->getJsonBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            return $this->errorResponse('Email and password are required');
        }
        
        // Authenticate
        $authenticator = new \SilverStripe\Security\MemberAuthenticator\MemberAuthenticator();
        $member = $authenticator->authenticate([
            'Email' => $email,
            'Password' => $password
        ], $request);
        
        if ($member) {
            // Login the member with "remember me" enabled for session persistence
            $identityStore = \SilverStripe\Core\Injector\Injector::inst()->get(IdentityStore::class);
            $identityStore->logIn($member, true, $request); // Set remember to true
            
            return $this->jsonResponse([
                'success' => true,
                'user' => $this->serializeMember($member),
                'message' => 'Login successful'
            ]);
        }
        
        return $this->errorResponse('Invalid credentials', 401);
    }
    
    /**
     * Logout endpoint
     */
    public function logout(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $identityStore = \SilverStripe\Core\Injector\Injector::inst()->get(IdentityStore::class);
        $identityStore->logOut($request);
        
        return $this->successResponse([], 'Logout successful');
    }
    
    /**
     * Serialize member for API response
     */
    private function serializeMember(Member $member): array
    {
        $data = [
            'ID'             => $member->ID,
            'Email'          => $member->Email,
            'FirstName'      => $member->FirstName,
            'Surname'        => $member->Surname,
            'Gravatar'       => $member->getGravatar(),
            'Hash'           => $member->Hash,
            'Username'        => $member->Username ?: null,
            'NameVisibility'  => $member->NameVisibility ?: 'full',
            'FoodPreference'  => $member->FoodPreference ?: 'None',
            'DateOfBirth'    => $member->DateOfBirth,
            'Joindate'       => $member->Joindate,
        ];

        // Add profile image if exists
        if ($member->ProfileImageID && $member->ProfileImage()->exists()) {
            $data['ProfileImage'] = [
                'URL' => $member->ProfileImage()->FillMax(300, 300)->getURL()
            ];
        }

        return $data;
    }
}
