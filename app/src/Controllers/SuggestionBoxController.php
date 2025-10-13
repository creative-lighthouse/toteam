<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\SuggestionBox\Suggestion;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Security;
use SilverStripe\Forms\DropdownField;

/**
 * Class \App\Controllers\SuggestionBoxController
 *
 */
class SuggestionBoxController extends BaseController
{
    private static $url_segment = 'suggestionbox';

    private static $allowed_actions = [
        'SuggestionBoxForm',
        'doAddSuggestion',
        'markasseen',
        'markallasseen',
    ];

    public function index()
    {
        $currentuser = Security::getCurrentUser();

        return $this->render([
            'User' => $currentuser
        ]);
    }

    public function SuggestionBoxForm()
    {
        $currentuser = Security::getCurrentUser();
        $adminuser = Member::get()->filter('ID', 1)->first();
        //Give all members except the current user
        $allmembers = Member::get()->filter('ID:not', $currentuser->ID)->filter('ID:not', $adminuser->ID)->map('ID', 'Name')->toArray();

        $fields = FieldList::create(
            TextField::create('Title', 'Worum geht es allgemein?', ''),
            TextField::create('Text', 'Bitte beschreibe dein Problem, deine Kritik oder Anmerkung:', ''),
            DropdownField::create('MemberID', 'Welches Mitglied betrifft deine Anmerkung?', $allmembers)->setEmptyString('Allgemeine Anmerkung'),
            CheckboxField::create('StayAnonymous', 'Ich möchte gerne anonym bleiben')->setValue(false)
        );

        $actions = FieldList::create(
            FormAction::create('doAddSuggestion', 'Absenden', 'AddSuggestion')
                ->setUseButtonTag(true)
                ->addExtraClass('button--form')
        );

        return Form::create($this, 'SuggestionBoxForm', $fields, $actions);
    }

    public function doAddSuggestion($data, $form)
    {
        $currentuser = Security::getCurrentUser();
        $newsuggestion = Suggestion::create();

        echo '<pre>';
        print_r($data);
        echo '</pre>';

        $newsuggestion->Title = $data['Title'];
        $newsuggestion->Description = $data['Text'];
        if (isset($data['StayAnonymous']) && $data['StayAnonymous']) {
            $newsuggestion->IsAnonymous = true;
        } else {
            $newsuggestion->IsAnonymous = false;
            $newsuggestion->SenderID = $currentuser->ID;
        }

        if ($data['MemberID']) {
            $member = Member::get()->byID($data['MemberID']);
            $newsuggestion->RecipientID = $member->ID;
            $newsuggestion->HasRecipient = true;
            $newsuggestion->SeenByRecipient = false;
        } else {
            $newsuggestion->HasRecipient = false;
            $newsuggestion->SeenByRecipient = true;
        }

        $newsuggestion->write();

        //Redirect to the same page with a success message
        $form->sessionMessage('Dein Eintrag wurde erfolgreich gesendet. Vielen Dank für dein Feedback!', 'good');
        return $this->redirectBack();
    }

    public function markasseen($request)
    {
        $suggestionID = $request->param('ID');
        $suggestion = Suggestion::get_by_id($suggestionID);
        if (!$suggestion) {
            return $this->httpError(404, 'Eintrag nicht gefunden');
        }

        $currentuser = Security::getCurrentUser();
        if (!$currentuser || $suggestion->RecipientID != $currentuser->ID) {
            return $this->httpError(403, 'Du hast keine Berechtigung, diesen Eintrag zu markieren');
        }

        $suggestion->SeenByRecipient = true;
        $suggestion->write();

        //Redirect to the same page with a success message
        $this->getResponse()->addHeader('X-Status', rawurlencode('Der Eintrag wurde als gelesen markiert.'));
        return $this->redirectBack();
    }

    public function markallasseen($request)
    {
        $currentuser = Security::getCurrentUser();
        if (!$currentuser) {
            return $this->httpError(403, 'Du hast keine Berechtigung, diese Einträge zu markieren');
        }

        $suggestions = Suggestion::get()->filter([
            'RecipientID' => $currentuser->ID,
            'SeenByRecipient' => false,
        ]);

        foreach ($suggestions as $suggestion) {
            $suggestion->SeenByRecipient = true;
            $suggestion->write();
        }

        //Redirect to the same page with a success message
        $this->getResponse()->addHeader('X-Status', rawurlencode('Alle Einträge wurden als gelesen markiert.'));
        return $this->redirectBack();
    }

    public function getNewSuggestions()
    {
        $currentuser = Security::getCurrentUser();
        if (!$currentuser) {
            return null;
        }

        return Suggestion::get()->filter([
            'RecipientID' => $currentuser->ID,
            'SeenByRecipient' => false,
        ]);
    }

    public function getOldSuggestions()
    {
        $currentuser = Security::getCurrentUser();
        if (!$currentuser) {
            return null;
        }

        //Filter suggestions where SeenByRecipient is true and the RecipientID is the current user or there is no recipient

        return Suggestion::get()->filterAny([
            'RecipientID' => $currentuser->ID,
            'HasRecipient' => false,
        ])->filter('SeenByRecipient', true)->sort('Created', 'DESC');
    }
}
