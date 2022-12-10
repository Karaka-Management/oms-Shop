<?php
/**
 * Karaka
 *
 * PHP Version 8.1
 *
 * @package   Template
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://karaka.app
 */
declare(strict_types=1);

use phpOMS\Uri\UriFactory;

?>
<footer>
    <div class="floater">
        <hr>
        <ul>
            <li><a href="<?= UriFactory::build('{/lang}/{/app}/{/app}/terms'); ?>">Terms</a>
            <li><a href="<?= UriFactory::build('{/lang}/{/app}/{/app}/privacy'); ?>">Data Protection</a>
            <li><a href="<?= UriFactory::build('{/lang}/{/app}/{/app}/imprint'); ?>">Imprint</a>
        </ul>
    </div>
</footer>