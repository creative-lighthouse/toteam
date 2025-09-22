<?php

use SilverStripe\Security\Validation\RulesPasswordValidator;
use SilverStripe\ORM\Search\FulltextSearchable;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;

// remove PasswordValidator for SilverStripe 5.0
$validator = RulesPasswordValidator::create();
// Settings are registered via Injector configuration - see passwords.yml in framework
Member::set_password_validator($validator);
i18n::set_locale('de_DE');
FulltextSearchable::enable();
