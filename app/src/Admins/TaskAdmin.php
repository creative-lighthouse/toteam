<?php

namespace App\Admins;

use App\Tasks\Task;
use App\Tasks\TaskGroup;
use Colymba\BulkManager\BulkAction\DeleteHandler;
use Colymba\BulkManager\BulkAction\EditHandler;
use Colymba\BulkManager\BulkManager;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;

/**
 * Class \App\Admins\TaskAdmin
 *
 */
class TaskAdmin extends ModelAdmin
{
    private static $menu_title = 'Tasks';

    private static $url_segment = 'tasks-directory';
    private static $menu_icon = 'app/client/icons/totems/todos_totem_admin.png';

    private static $managed_models = [
        Task::class,
        TaskGroup::class,
    ];

    /**
     * The default export uses summaryFields() (Title only, with the translated
     * "Titel" label as CSV header), which both leaves out most of the data and
     * breaks re-importing the file: CsvBulkLoader maps CSV headers directly to
     * field names, so a "Titel" column silently fails to map to Task::$Title.
     * Exporting the raw field/relation-ID names keeps export and import symmetric.
     */
    public function getExportFields()
    {
        if ($this->getModelClass() === Task::class) {
            return [
                'ID'             => 'ID',
                'Title'          => 'Title',
                'Description'    => 'Description',
                'Deadline'       => 'Deadline',
                'State'          => 'State',
                'OrganizationID' => 'OrganizationID',
                'OwnerID'        => 'OwnerID',
                'ParentID'       => 'ParentID',
            ];
        }

        return parent::getExportFields();
    }

    /**
     * Adds bulk edit/delete actions to the Task GridField. This is deliberately
     * NOT inline editing (GridFieldEditableColumns): selecting rows and choosing
     * "Edit" opens a separate bulk-edit form (one collapsible section per record),
     * "Delete" removes all selected records at once.
     */
    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);

        if ($this->getModelClass() === Task::class) {
            /** @var GridField $gridField */
            $gridField = $form->Fields()->dataFieldByName($this->sanitiseClassName(Task::class));
            $gridFieldConfig = $gridField->getConfig();

            $bulkManager = BulkManager::create([
                'Title',
                'Description',
                'Deadline',
                'State',
                'OwnerID',
                'OrganizationID',
            ], false);

            $bulkManager
                ->addBulkAction(EditHandler::class)
                ->addBulkAction(DeleteHandler::class);

            $gridFieldConfig->addComponent($bulkManager);
        }

        return $form;
    }
}
