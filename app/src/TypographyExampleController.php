<?php

namespace App\Pages {

    use SilverStripe\Control\HTTPRequest;
    use SilverStripe\Control\Controller;

    /**
 * Class \App\Pages\TypographyExampleController
 *
 */
class TypographyExampleController extends Controller
    {
        private static $allowed_actions = [];
        private static $url_segment = 'typographyexample';

        public function index(HTTPRequest $request)
        {
            echo 'Hello World';
            return [];
        }
    }
}
