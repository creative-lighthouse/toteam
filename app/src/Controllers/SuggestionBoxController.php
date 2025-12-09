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
use SilverStripe\Forms\HiddenField;
use SilverStripe\Model\List\ArrayList;
use App\Teams\Organization;

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
        if (!$currentuser) {
            return null;
        }

        // Get user's organizations
        $organizationIDs = $currentuser->getOrganizationIDs();
        if (empty($organizationIDs)) {
            // No organizations - can't create suggestions
            return null;
        }

        $adminuser = Member::get()->filter('ID', 1)->first();
        //Give all members except the current user
        $allmembers = Member::get()->filter('ID:not', $currentuser->ID)->filter('ID:not', $adminuser->ID)->map('ID', 'Name')->toArray();

        $fields = FieldList::create(
            TextField::create('Title', 'Worum geht es allgemein?', ''),
            TextField::create('Text', 'Bitte beschreibe dein Problem, deine Kritik oder Anmerkung:', ''),
            DropdownField::create('MemberID', 'Welches Mitglied betrifft deine Anmerkung?', $allmembers)->setEmptyString('Allgemeine Anmerkung'),
            CheckboxField::create('StayAnonymous', 'Ich möchte gerne anonym bleiben')->setValue(false)
        );

        // Add organization field based on how many organizations the user belongs to
        if (count($organizationIDs) > 1) {
            // Multiple organizations - show dropdown
            $organizations = Organization::get()->filter('ID', $organizationIDs)->map('ID', 'Title')->toArray();
            $fields->push(
                DropdownField::create('ParentID', 'Organisation', $organizations)
                    ->setEmptyString('-- Bitte wählen --')
            );
        } else {
            // Single organization - use hidden field
            $fields->push(
                HiddenField::create('ParentID', 'ParentID', $organizationIDs[0])
            );
        }

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
        if (!$currentuser) {
            return $this->httpError(403, 'Nicht autorisiert');
        }

        // Validate organization access
        $organizationIDs = $currentuser->getOrganizationIDs();
        if (empty($organizationIDs)) {
            $form->sessionMessage('Du gehörst keiner Organisation an und kannst keine Einträge erstellen.', 'bad');
            return $this->redirectBack();
        }

        if (!isset($data['ParentID']) || !in_array($data['ParentID'], $organizationIDs)) {
            $form->sessionMessage('Ungültige Organisation ausgewählt.', 'bad');
            return $this->redirectBack();
        }

        $newsuggestion = Suggestion::create();

        $newsuggestion->Title = $data['Title'];
        $newsuggestion->Description = $data['Text'];
        $newsuggestion->ParentID = $data['ParentID'];

        if (isset($data['StayAnonymous']) && $data['StayAnonymous']) {
            $newsuggestion->IsAnonymous = true;
        } else {
            $newsuggestion->IsAnonymous = false;
            $newsuggestion->SenderID = $currentuser->ID;
        }

        if ($data['MemberID']) {
            $member = Member::get()->byID($data['MemberID']);
            $newsuggestion->RecipientID = $member->ID;
            $newsuggestion->SeenByRecipient = false;
        }
        // If no MemberID, RecipientID stays null (entry is for all)

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

        // Check organization access
        $organizationIDs = $this->getUserOrganizationIDs();
        if (!empty($organizationIDs) && !in_array($suggestion->ParentID, $organizationIDs)) {
            return $this->httpError(403, 'Access denied');
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
        ])->exclude('RecipientID', 0);

        $suggestions = $this->filterByUserOrganizations($suggestions);

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

        // Only entries directly addressed to the user that haven't been seen yet
        $suggestions = Suggestion::get()->filter([
            'RecipientID' => $currentuser->ID,
            'SeenByRecipient' => false,
        ])->exclude('RecipientID', 0);

        return $this->filterByUserOrganizations($suggestions);
    }

    public function getOldSuggestions()
    {
        $currentuser = Security::getCurrentUser();
        if (!$currentuser) {
            return null;
        }

        // Get entries that are either:
        // 1. Addressed to user and have been seen
        // 2. Have no recipient (to all) - these are always visible to organization members

        // Create an ArrayList to combine results
        $allSuggestions = ArrayList::create();

        // Get personal seen suggestions
        $personalSeen = Suggestion::get()->filter([
            'RecipientID' => $currentuser->ID,
            'SeenByRecipient' => true,
        ]);
        $personalSeen = $this->filterByUserOrganizations($personalSeen);
        foreach ($personalSeen as $suggestion) {
            $allSuggestions->push($suggestion);
        }

        // Get suggestions for all (no recipient)
        $toAll = Suggestion::get()->filter('RecipientID', 0);
        $toAll = $this->filterByUserOrganizations($toAll);
        foreach ($toAll as $suggestion) {
            $allSuggestions->push($suggestion);
        }

        // Sort by Created date descending
        return $allSuggestions->sort('Created', 'DESC');
    }
}
