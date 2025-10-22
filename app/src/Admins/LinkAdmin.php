<?php

namespace App\Admins;

use App\Links\TeamLink;
use App\Links\TeamLinkType;
use SilverStripe\Forms\FieldList;
use SilverStripe\Admin\ModelAdmin;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class \App\Admins\NoticesAdmin
 *
 */
class LinkAdmin extends ModelAdmin
{
    private static $menu_title = 'Links & Downloads';

    private static $url_segment = 'links-directory';
    private static $menu_icon = 'app/client/icons/totems/downloads_totem_admin.png';

    private static $managed_models = [
        TeamLink::class,
        TeamLinkType::class,
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        $grid = $form->Fields()->dataFieldByName('App-Links-TeamLink');
        if ($grid) {
            $grid->getConfig()->addComponent(new GridFieldOrderableRows('SortOrder'));
        }
        return $form;
    }
}
