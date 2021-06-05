<?php
/**
 * Orange Management
 *
 * PHP Version 8.0
 *
 * @package   Modules\Shop\Admin\Install
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://orange-management.org
 */
declare(strict_types=1);

namespace Modules\Shop\Admin\Install;

use Model\Setting;
use Model\SettingMapper;
use phpOMS\DataStorage\Database\DatabasePool;

/**
 * CMS class.
 *
 * @package Modules\Shop\Admin\Install
 * @license OMS License 1.0
 * @link    https://orange-management.org
 * @since   1.0.0
 */
class CMS
{
    /**
     * Install media providing
     *
     * @param string       $path   Module path
     * @param DatabasePool $dbPool Database pool for database interaction
     *
     * @return void
     *
     * @since 1.0.0
     */
    public static function install(string $path, DatabasePool $dbPool) : void
    {
        $app = \Modules\CMS\Admin\Installer::installExternal($dbPool, ['path' => __DIR__ . '/CMS.install.json']);
    }
}
