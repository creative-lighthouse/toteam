<?php

namespace App\Food;

use App\Food\Food;
use App\Food\MealEater;
use App\Events\EventDay;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use App\Notifications\PushNotificationService;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

/**
 * Class \App\Food\Meal
 *
 * @property ?string $Title
 * @property ?string $Time
 * @property int $ParentID
 * @method \App\Events\EventDay Parent()
 * @method \SilverStripe\ORM\DataList|\App\Food\MealEater[] Eaters()
 * @method \SilverStripe\ORM\ManyManyList|\App\Food\Food[] Foods()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Meal extends DataObject implements PermissionProvider
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Time" => "Time"
    ];

    private static $has_one = [
        "Parent" => EventDay::class,
    ];

    private static $has_many = [
        "Eaters" => MealEater::class,
    ];

    private static $belongs_many_many = [
        "Foods" => Food::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Time" => "Uhrzeit",
        "Eaters" => "Teilnehmer",
        "Foods" => "Gerichte",
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Time" => "Uhrzeit",
        "Parent.Title" => "Tag",
        "Parent.Date.Nice" => "Datum",
    ];

    private static $searchable_fields = [
        "Title",
        "Time",
        "Parent.Title",
        "Parent.Date",
    ];

    private static $table_name = 'Meal';
    private static $singular_name = "Mahlzeit";
    private static $plural_name = "Mahlzeiten";
    private static $default_sort = 'Time ASC';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ParentID');

        $eatersGridfield = $fields->dataFieldByName('Eaters');
        $fields->removeByName('Eaters');
        if ($eatersGridfield) {
            $eatersGridfield->setConfig(GridFieldConfig_RecordEditor::create());
            $fields->addFieldToTab('Root.Main', $eatersGridfield);
        }
        return $fields;
    }

    public function RenderTime()
    {
        return $this->dbObject('Time')->Format('HH:mm');
    }

    public function getMealParticipationOfCurrentUser()
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return null;
        }
        return MealEater::get()->filter(['ParentID' => $this->ID, 'MemberID' => $member->ID])->first();
    }

    public function getDetailsLink()
    {
        return '/food/meal/' . $this->ID;
    }

    /**
     * Send push notification for new meals
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Only send notification for newly created meals
        $changedFields = $this->getChangedFields(false, 1);
        $isNew = isset($changedFields['ID']) && empty($changedFields['ID']['before']);

        if ($isNew) {
            PushNotificationService::notifyNewMeal($this);
        }
    }

    public function providePermissions()
    {
        return [
            'CREATE_MEALS' => [
                'name' => 'Mahlzeiten erstellen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Erstellen, von Mahlzeiten'
            ],
            'EDIT_MEALS' => [
                'name' => 'Mahlzeiten bearbeiten',
                'category' => 'Essen',
                'help' => 'Erlaubt das Bearbeiten von Mahlzeiten'
            ],
            'VIEW_MEALS' => [
                'name' => 'Mahlzeiten ansehen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Ansehen von Mahlzeiten'
            ],
            'DELETE_MEALS' => [
                'name' => 'Mahlzeiten löschen',
                'category' => 'Essen',
                'help' => 'Erlaubt das Löschen von Mahlzeiten'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MEALS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_MEALS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MEALS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MEALS');
    }
}
