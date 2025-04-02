<?php
/**
 * Jingga
 *
 * PHP Version 8.2
 *
 * @package   Modules\Shop\Models
 * @copyright Dennis Eichhorn
 * @license   OMS License 2.2
 * @version   1.0.0
 * @link      https://jingga.app
 */
declare(strict_types=1);

namespace Modules\Shop\Models;

use phpOMS\Stdlib\Base\Enum;

/**
 * Permission category enum.
 *
 * @package Modules\Shop\Models
 * @license OMS License 2.2
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
