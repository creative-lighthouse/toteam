<?php

namespace App\Pages;

use PageController;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Security;

class SuggestionBoxPageController extends PageController
{
    private static $allowed_actions = [
    ];

    public function index()
    {
        $currentuser = Security::getCurrentUser();

        return [
            'User' => $currentuser
        ];
    }

    public function SuggestionBoxForm()
    {
        $fields = FieldList::create(
            TextField::create('Titel', 'Titel', 'Title'),
            TextField::create('Text', 'Text', 'Text')
        );

        $actions = FieldList::create(
            FormAction::create('doAddSuggestion', 'Absenden', 'AddSuggestion')
                ->setUseButtonTag(true)
                ->addExtraClass('button--form')
        );

        return Form::create($this, 'SuggestionBoxForm', $fields, $actions);
    }
}
