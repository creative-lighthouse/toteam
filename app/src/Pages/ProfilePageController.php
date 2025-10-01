<?php

namespace App\Pages;

use PageController;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Security;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\FieldType\DBField;

class ProfilePageController extends PageController
{
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
            $upload = new FileField("ProfileImage", "Profile Image");
            $upload->setFolderName("ProfileImages");
            $upload->setAllowedFileCategories("image");
            $textFieldFirstName = new TextField("FirstName", "First Name");
            $textFieldLastName = new TextField("Surname", "Last Name");
            $textFieldEmail = new TextField("Email", "Email");
            $dateFieldBirthdate = new DateField("DateOfBirth", "Birthdate");
            $dropdownFieldFoodPreference = DropdownField::create('FoodPreference', 'Essenspräferenz', [
                'None' => 'Keine Besonderheiten',
                'Vegetarian' => 'Vegetarisch',
                'Vegan' => 'Vegan',
            ]);
            $dropdownFieldFoodPreference->setValue($currentUser->FoodPreference);

            $fields = new FieldList(
                $upload,
                $textFieldFirstName,
                $textFieldLastName,
                $textFieldEmail,
                $dateFieldBirthdate,
                $dropdownFieldFoodPreference
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
}
