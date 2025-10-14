<?php
namespace {

use SilverStripe\Security\Security;

    use SilverStripe\CMS\Controllers\ContentController;

    /**
 * Class \PageController
 *
 * @property \Page $dataRecord
 * @method \Page data()
 * @mixin \Page
 */
    class PageController extends ContentController
    {
        private static $allowed_actions = [];

        protected function init()
        {
            parent::init();
            // You can include any CSS or JS required by your project here.
            // See: https://docs.silverstripe.org/en/developer_guides/templates/requirements/


            //Redirect the user to '/dashboard' if he is logged in and visits the home page or to '/registration' if not logged in
            if ($this->dataRecord->URLSegment == 'home') {
                if (Security::getCurrentUser()) {
                    $this->redirect('/dashboard');
                } else {
                    $this->redirect('/Security/login?BackURL=/dashboard');
                }
            }
        }
    }
}
