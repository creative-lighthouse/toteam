<?php

namespace {

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

    use App\Pages\RegistrationPage;
    use SilverStripe\CMS\Controllers\ContentController;
    use SilverStripe\Security\Security;

    class PageController extends ContentController
    {
        protected function init()
        {
            parent::init();

            $registrationPage = RegistrationPage::get()->first();

            //Check if currently on registration or login page
            $currentURL = $this->getRequest()->getURL();
            if($registrationPage && ($currentURL === $registrationPage->URLSegment || $currentURL === 'Security/login')) {
                return;
            }

            if(Security::getCurrentUser()) {
                //Logged in
            } else {
                //Not logged in
                if($registrationPage) {
                    return $this->redirect($registrationPage->Link());
                } else {
                    //return $this->httpError(404, 'Du musst eingeloggt sein um diesen Dienst zu nutzen');
                }
            }
        }
    }
}
