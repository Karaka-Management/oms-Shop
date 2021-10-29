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

use phpOMS\Application\ApplicationAbstract;

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
     * @param string              $path Module path
     * @param ApplicationAbstract $app  Application
     *
     * @return void
     *
     * @since 1.0.0
     */
    public static function install(string $path, ApplicationAbstract $app) : void
    {
        $app = \Modules\CMS\Admin\Installer::installExternal($app, ['path' => __DIR__ . '/CMS.install.json']);
    }
}
