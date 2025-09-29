<?php

namespace App\Pages;

use PageController;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Member_Validator;
use SilverStripe\Forms\ConfirmedPasswordField;

class RegistrationPageController extends PageController
{
    private static $allowed_actions = [
        'RegistrationForm',
        'doRegister',
        'confirm',
    ];

    public function RegistrationForm()
    {
        $fields = FieldList::create(
            TextField::create('FirstName', _t('Registration.FIRST_NAME', 'First Name')),
            TextField::create('Surname', _t('Registration.SURNAME', 'Surname')),
            EmailField::create('Email', _t('Registration.EMAIL', 'Email Address')),
            ConfirmedPasswordField::create('Password', _t('Registration.PASSWORD', 'Password'))->addExtraClass('password-field')->setAttribute('minlength', 8)
        );

        $actions = FieldList::create(
            FormAction::create('doRegister', _t('Registration.SUBMIT', 'Register'))
                ->setUseButtonTag(true)
                ->addExtraClass('button--form')

        );

        $validator = new Member_Validator();

        return Form::create($this, 'RegistrationForm', $fields, $actions, $validator);
    }

    public function doRegister($data, $form)
    {
        $member = Member::create();
        $form->saveInto($member);
        $member->write();

        return $this->redirect('Security/login?BackURL=%2Fhome&registration=1');
    }
}
