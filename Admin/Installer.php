<?php
/**
 * Orange Management
 *
 * PHP Version 7.4
 *
 * @package   Modules\Shop\Admin
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://orange-management.org
 */
declare(strict_types=1);

namespace Modules\Shop\Admin;

use phpOMS\DataStorage\Database\DatabasePool;
use phpOMS\Module\InstallerAbstract;
use phpOMS\Module\ModuleInfo;
use phpOMS\System\File\Local\Directory;
use phpOMS\Config\SettingsInterface;

/**
 * Installer class.
 *
 * @package Modules\Shop\Admin
 * @license OMS License 1.0
 * @link    https://orange-management.org
 * @since   1.0.0
 */
final class Installer extends InstallerAbstract
{
    /**
     * Path of the file
     *
     * @var string
     * @since 1.0.0
     */
    public const PATH = __DIR__;

    /**
     * {@inheritdoc}
     */
    public static function install(DatabasePool $dbPool, ModuleInfo $info, SettingsInterface $cfgHandler) : void
    {
        if (\file_exists(__DIR__ . '/../../../Web/Shop')) {
            Directory::delete(__DIR__ . '/../../../Web/Shop');
        }

        parent::install($dbPool, $info, $cfgHandler);
    }
}
