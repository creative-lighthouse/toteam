<?php

namespace App\Controllers;

use SilverStripe\Forms\Form;
use App\HumanResources\Allergy;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Forms\FormAction;
use App\Controllers\BaseController;
use SilverStripe\Security\Security;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CheckboxSetField;

/**
 * Class \App\Controllers\ProfileController
 *
 */
class ProfileController extends BaseController
{
    private static $url_segment = 'profile';

    private static $allowed_actions = [
        'EditProfileForm',
        'editProfile',
    ];

    public function index()
    {
        $currentUser = Security::getCurrentUser();
        return [
            'CurrentUser' => $currentUser,
        ];
    }

    public function EditProfileForm()
    {
        $currentMember = Security::getCurrentUser();
        if (!$currentMember) {
            return;
        }
        $currentUser = Member::get()->filter("ID", $currentMember->ID)->first();
        if ($currentUser) {
            $upload = new FileField("ProfileImage", "Profilbild");
            $upload->setFolderName("ProfileImages");
            $upload->setAllowedFileCategories("image");
            $textFieldFirstName = new TextField("FirstName", "Vorname");
            $textFieldLastName = new TextField("Surname", "Nachname");
            $textFieldEmail = new TextField("Email", "E-Mail");
            $dateFieldBirthdate = new DateField("DateOfBirth", "Geburtsdatum");
            $dropdownFieldFoodPreference = DropdownField::create('FoodPreference', 'Essenspräferenz', [
                'None' => 'Keine Besonderheiten',
                'Vegetarian' => 'Vegetarisch',
                'Vegan' => 'Vegan',
            ]);
            $dropdownFieldFoodPreference->setValue($currentUser->FoodPreference);
            $allergies = Allergy::get();
            if ($allergies->count() > 0) {
                $dropdownFieldAllergies = CheckboxSetField::create('Allergies', 'Allergien', $allergies->map('ID', 'Title'));
            }

            $fields = new FieldList(
                $upload,
                $textFieldFirstName,
                $textFieldLastName,
                $textFieldEmail,
                $dateFieldBirthdate,
                $dropdownFieldFoodPreference,
                $dropdownFieldAllergies ?? null
            );

            $actions = new FieldList(
                new FormAction("editProfile", "Profil speichern")
            );

            $form = new Form($this, "EditProfileForm", $fields, $actions);
            $form->loadDataFrom($currentUser);
            return $form;
        }
        return null;
    }

    public function editProfile($data, $form)
    {
        $currentMember = Security::getCurrentUser();
        if (!$currentMember) {
            return;
        }
        $currentUser = Member::get()->filter("ID", $currentMember->ID)->first();
        if ($currentUser) {
            $form->saveInto($currentUser);
            $currentUser->write();
            $form->sessionMessage("Profile updated successfully.", "good");
            return $this->redirectBack();
        }
        return null;
    }

    public function getLogoutLink()
    {

    }
}
