<?php declare(strict_types=1);

use Modules\Shop\Controller\ShopController;
use Modules\Shop\Models\PermissionCategory;
use phpOMS\Account\PermissionType;
use phpOMS\Router\RouteVerb;

return [
    '^(\/)(\?.*)*$' => [
        [
            'dest'       => '\Modules\Shop\Controller\ShopController:viewWelcome',
            'verb'       => RouteVerb::GET,
            'permission' => [
                'module' => ShopController::NAME,
                'type'   => PermissionType::READ,
                'state'  => PermissionCategory::SHOP,
            ],
        ],
    ],
];
