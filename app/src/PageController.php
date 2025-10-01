<?php

namespace {

    use App\Pages\ProfilePage;
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
            if ($registrationPage && ($currentURL === $registrationPage->URLSegment || $currentURL === 'Security/login')) {
                return;
            }

            //Check if currently trying to register
            if ($this->getRequest()->getVar('Action') === 'doRegister') {
                return;
            }
        }

        public function getLogoutLink()
        {
            return Security::logout_url();
        }

        public function getProfileLink()
        {
            $profilePage = ProfilePage::get()->first();
            if ($profilePage) {
                return $profilePage->Link();
            }
        }

        public function getCurrentUser()
        {
            return Security::getCurrentUser();
        }
    }
}
