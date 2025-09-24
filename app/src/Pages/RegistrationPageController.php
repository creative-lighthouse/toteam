<?php

namespace App\Pages;

use Override;
use PageController;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\ConfirmedPasswordField;
use SilverStripe\Security\Member_Validator;

class LoginPageController extends PageController
{
    private static $allowed_actions = [
        'LoginForm',
        'confirm',
    ];

    #[Override]
    public function LoginForm()
    {

        $fields = FieldList::create(
            TextField::create('FirstName', _t('Login.FIRST_NAME', 'First Name')),
            TextField::create('Surname', _t('Login.SURNAME', 'Surname')),
            EmailField::create('Email', _t('Login.EMAIL', 'Email Address')),
            ConfirmedPasswordField::create('Password', _t('Login.PASSWORD', 'Password'))->addExtraClass('password-field')->setAttribute('minlength', 8)
        );

        $actions = FieldList::create(
            FormAction::create('doLogin', _t('Login.SUBMIT', 'Login'))
                ->setUseButtonTag(true)
                ->addExtraClass('button--form')

        );

        $validator = new Member_Validator();

        return Form::create($this, 'RegistrationForm', $fields, $actions, $validator);
    }

    public function doRegister($data, $form)
    {
        return $this->redirect('Security/login?BackURL=%2Fhome&registration=1');
    }

    public function confirm($request)
    {
        $hash = $request->param('ID');
        return $this->redirect('Security/login?BackURL=%2Fhome&registration=2');
    }
}
