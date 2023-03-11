<?php
/**
 * Karaka
 *
 * PHP Version 8.1
 *
 * @package   Modules\Shop\Models
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://jingga.app
 */
declare(strict_types=1);

namespace Modules\Shop\Models;

use phpOMS\Stdlib\Base\Enum;

/**
 * Permision state enum.
 *
 * @package Modules\Shop\Models
 * @license OMS License 1.0
 * @link    https://jingga.app
 * @since   1.0.0
 */
abstract class PermissionCategory extends Enum
{
    public const ARTICLE = 1;

    public const BUYER = 2;

    public const SELLER = 3;

    public const SHOP = 4;

    public const BUY = 5;
}
