<?php
/**
 * Karaka
 *
 * PHP Version 8.1
 *
 * @package   Modules
 * @copyright Dennis Eichhorn
 * @license   OMS License 2.0
 * @version   1.0.0
 * @link      https://jingga.app
 */
declare(strict_types=1);

use Modules\Shop\Controller\ApiController;
use Modules\Shop\Models\PermissionCategory;
use phpOMS\Account\PermissionType;
use phpOMS\Router\RouteVerb;

return [
    '^.*/shop/oneclick/buy.*$' => [
        [
            'dest'       => '\Modules\Shop\Controller\ApiController:apiOneClickBuy',
            'verb'       => RouteVerb::GET,
            'permission' => [
                'module' => ApiController::NAME,
                'type'   => PermissionType::CREATE,
                'state'  => PermissionCategory::BUY,
            ],
        ],
    ],
    '^.*/shop/media/download.*$' => [
        [
            'dest'       => '\Modules\Shop\Controller\ApiController:apiItemFileDownload',
            'verb'       => RouteVerb::GET,
            'permission' => [
            ],
        ],
    ],
];
