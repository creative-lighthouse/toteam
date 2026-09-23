<?php

use SilverStripe\Security\Validation\RulesPasswordValidator;
use SilverStripe\ORM\Search\FulltextSearchable;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;

// remove PasswordValidator for SilverStripe 5.0
$validator = RulesPasswordValidator::create();
// Settings are registered via Injector configuration - see passwords.yml in framework
// Don't block reusing a previous password. This validator is shared by the
// CMS admin login and ToTeam's own optional password login
// (App\Controllers\Api\ProfileApiController::setPassword()) — SilverStripe
// has no per-context validator, so this applies to both.
$validator->setHistoricCount(0);
Member::set_password_validator($validator);
i18n::set_locale('de_DE');
FulltextSearchable::enable();
