<?php
/**
 * Karaka
 *
 * PHP Version 7.4
 *
 * @package   Modules\Shop\Admin
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://karaka.app
 */
declare(strict_types=1);

namespace Modules\Shop\Admin;

use phpOMS\Module\StatusAbstract;

/**
 * Shop class.
 *
 * @package Modules\Shop\Admin
 * @license OMS License 1.0
 * @link    https://karaka.app
 * @since   1.0.0
 */
final class Status extends StatusAbstract
{
    /**
     * Path of the file
     *
     * @var string
     * @since 1.0.0
     */
    public const PATH = __DIR__;
}
