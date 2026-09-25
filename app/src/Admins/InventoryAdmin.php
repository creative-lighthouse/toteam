<?php

namespace App\Admins;

use App\Inventory\InventoryDamageReport;
use App\Inventory\InventoryItem;
use App\Inventory\InventoryItemType;
use App\Inventory\InventoryRental;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admins\InventoryAdmin
 *
 */
class InventoryAdmin extends ModelAdmin
{
    private static $menu_title = 'Inventar';

    private static $url_segment = 'inventory';
    private static $menu_icon = 'app/client/icons/totems/inventar_totem_admin.png';

    private static $managed_models = [
        InventoryItem::class,
        InventoryItemType::class,
        InventoryRental::class,
        InventoryDamageReport::class,
    ];
}
