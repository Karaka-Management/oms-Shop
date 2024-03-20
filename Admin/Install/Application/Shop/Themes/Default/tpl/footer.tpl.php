<?php
/**
 * Jingga
 *
 * PHP Version 8.2
 *
 * @package   Template
 * @copyright Dennis Eichhorn
 * @license   OMS License 2.0
 * @version   1.0.0
 * @link      https://jingga.app
 */
declare(strict_types=1);

use phpOMS\Uri\UriFactory;

?>
<footer>
    <div class="floater">
        <hr>
        <ul>
            <li><a href="<?= UriFactory::build('{/base}/{/app}/terms'); ?>">Terms</a>
            <li><a href="<?= UriFactory::build('{/base}/{/app}/privacy'); ?>">Data Protection</a>
            <li><a href="<?= UriFactory::build('{/base}/{/app}/imprint'); ?>">Imprint</a>
        </ul>
    </div>
</footer>