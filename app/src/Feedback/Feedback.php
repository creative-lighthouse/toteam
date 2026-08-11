<?php

namespace App\Feedback;

use App\Tasks\Task;
use App\Teams\Organization;
use SilverStripe\Control\Email\Email;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * Class \App\Feedback\Feedback
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $Status
 * @property ?string $Type
 * @property ?string $URL
 * @property bool $NotifyByEmail
 * @property int $SubmitterID
 * @property int $LinkedTaskID
 * @method \SilverStripe\Security\Member Submitter()
 * @method \App\Tasks\Task LinkedTask()
 */
class Feedback extends DataObject
{
    private static $table_name = 'Feedback';

    private static $singular_name = 'Feedback';
    private static $plural_name = 'Feedback';

    private static $db = [
        'Title'         => 'Varchar(255)',
        'Description'   => 'Text',
        'Status'        => "Enum('New,NextUp,InProgress,Rejected,Completed', 'New')",
        'Type'          => "Enum('BugReport,FeatureRequest', 'BugReport')",
        'URL'           => 'Varchar(255)',
        'NotifyByEmail' => 'Boolean',
    ];

    private static $has_one = [
        'Submitter'  => Member::class,
        'LinkedTask' => Task::class,
    ];

    private static $default_sort = 'Status ASC, Created DESC';

    private static $field_labels = [
        'Title'         => 'Titel',
        'Description'   => 'Beschreibung',
        'Status'        => 'Status',
        'Type'          => 'Typ',
        'URL'           => 'URL',
        'NotifyByEmail' => 'Per E-Mail benachrichtigen',
        'Submitter'     => 'Absender',
        'LinkedTask'    => 'Verknüpfte Aufgabe',
    ];

    private static $summary_fields = [
        'Title'              => 'Titel',
        'Type'                => 'Typ',
        'Status'              => 'Status',
        'Submitter.Title'     => 'Absender',
        'URL'                 => 'URL',
        'RenderNiceDate'      => 'Eingereicht',
    ];

    public function canCreate($member = null, $context = [])
    {
        return $member !== null;
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'ADMIN');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'ADMIN');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'ADMIN');
    }

    public function RenderNiceDate()
    {
        return $this->dbObject('Created')->Format('dd.MM.yyyy');
    }

    private bool $statusChanged = false;

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $isNew = !$this->isInDB();
        $this->statusChanged = $isNew ? false : $this->isChanged('Status');

        // Neuanlage: Verknüpfte Aufgabe VOR dem eigentlichen Insert anlegen, damit
        // LinkedTaskID im selben Schreibvorgang mitgespeichert wird — kein
        // rekursiver zweiter write() auf dieses Objekt nötig.
        if ($isNew && !$this->LinkedTaskID) {
            $this->createLinkedTask();
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if ($this->statusChanged && $this->NotifyByEmail) {
            $this->notifySubmitter();
        }
    }

    /**
     * Legt für neu eingereichtes Feedback automatisch eine Aufgabe in der
     * "toteam"-Organisation an, damit das Entwicklerteam es in seiner
     * bestehenden Aufgaben-Übersicht sieht, und setzt LinkedTaskID. Schlägt
     * das fehl (z.B. weil die Organisation in einer lokalen Dev-Umgebung
     * nicht existiert), darf das das Einreichen des Feedbacks nicht verhindern.
     */
    private function createLinkedTask(): void
    {
        try {
            $org = Organization::get()->filter('Username', 'toteam')->first();
            if (!$org || !$org->exists()) {
                return;
            }

            $typeLabel = $this->Type === 'FeatureRequest' ? 'Feature' : 'Bug';

            $description = $this->Description;
            if ($this->URL) {
                $description .= "\n\nURL: " . $this->URL;
            }
            $submitter = $this->Submitter();
            if ($submitter && $submitter->exists()) {
                $description .= "\nGemeldet von: " . $submitter->getDisplayName();
            }

            $task = Task::create();
            $task->Title = "[{$typeLabel}] {$this->Title}";
            $task->Description = $description;
            $task->State = 'open';
            $task->OrganizationID = $org->ID;

            if ($submitter && $submitter->exists() && $submitter->isActiveMemberOfOrg($org)) {
                $task->OwnerID = $submitter->ID;
            }

            $task->write();

            $this->LinkedTaskID = $task->ID;
        } catch (\Throwable $e) {
            error_log('Feedback::createLinkedTask fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Best-effort Benachrichtigung des Absenders bei Status-Änderung.
     */
    private function notifySubmitter(): void
    {
        try {
            $submitter = $this->Submitter();
            if (!$submitter || !$submitter->exists() || !$submitter->Email) {
                return;
            }

            $statusLabels = [
                'New'        => 'Neu',
                'NextUp'     => 'Als Nächstes',
                'InProgress' => 'In Bearbeitung',
                'Rejected'   => 'Abgelehnt',
                'Completed'  => 'Erledigt',
            ];
            $statusLabel = $statusLabels[$this->Status] ?? $this->Status;

            $email = Email::create()
                ->setTo($submitter->Email)
                ->setSubject("Dein Feedback \"{$this->Title}\" ist jetzt {$statusLabel}")
                ->setBody("Hallo {$submitter->getDisplayName()},\n\ndein Feedback \"{$this->Title}\" wurde als \"{$statusLabel}\" markiert.\n\nDein ToTeam-Team");
            $email->send();
        } catch (\Throwable $e) {
            error_log('Feedback::notifySubmitter fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
