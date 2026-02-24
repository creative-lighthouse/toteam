<?php

namespace App\Controllers\Api;

use App\Controllers\ApiController;
use App\Notices\Notice;
use App\Notices\NoticeCategory;
use App\Notices\NoticeReadStatus;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Notices API Controller
 *
 */
class NoticesApiController extends ApiController
{
    private static $url_segment = 'api/v1/notices';
    
    private static $allowed_actions = [
        'index',
        'read'
    ];
    
    private static $url_handlers = [
        '$ID/read' => 'read',
        '' => 'index'
    ];
    
    /**
     * Get all notices
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        
        // Get all notices
        $notices = Notice::get()->sort('Created DESC');
        
        $noticesData = [];
        foreach ($notices as $notice) {
            // Check if read by current user
            $isRead = NoticeReadStatus::get()->filter([
                'NoticeID' => $notice->ID,
                'MemberID' => $member->ID
            ])->exists();
            
            $noticesData[] = [
                'ID' => $notice->ID,
                'Title' => $notice->Title,
                'Content' => $notice->Content,
                'Created' => $notice->dbObject('Created')->Nice(),
                'CategoryID' => $notice->CategoryID,
                'Category' => $notice->Category()->exists() ? [
                    'ID' => $notice->Category()->ID,
                    'Title' => $notice->Category()->Title
                ] : null,
                'IsRead' => $isRead
            ];
        }
        
        // Get all categories
        $categories = NoticeCategory::get();
        $categoriesData = [];
        foreach ($categories as $category) {
            $categoriesData[] = [
                'ID' => $category->ID,
                'Title' => $category->Title
            ];
        }
        
        return $this->jsonResponse([
            'notices' => $noticesData,
            'categories' => $categoriesData
        ]);
    }
    
    /**
     * Mark notice as read
     */
    public function read(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $member = $this->requireAuth();
        $noticeID = $request->param('ID');
        
        if (!$noticeID) {
            return $this->errorResponse('Notice ID required');
        }
        
        $notice = Notice::get()->byID($noticeID);
        
        if (!$notice) {
            return $this->errorResponse('Notice not found', 404);
        }
        
        // Check if already marked as read
        $existingStatus = NoticeReadStatus::get()->filter([
            'NoticeID' => $noticeID,
            'MemberID' => $member->ID
        ])->first();
        
        if (!$existingStatus) {
            $status = NoticeReadStatus::create();
            $status->NoticeID = $noticeID;
            $status->MemberID = $member->ID;
            $status->write();
        }
        
        return $this->successResponse([], 'Notice marked as read');
    }
}
