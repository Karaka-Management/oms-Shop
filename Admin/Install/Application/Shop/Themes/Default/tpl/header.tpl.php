<?php
/**
 * Karaka
 *
 * PHP Version 8.0
 *
 * @package   Web\{APPNAME}
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://karaka.app
 */
declare(strict_types=1);

use phpOMS\Uri\UriFactory;

?>
<header>
    <nav>
       <ul>
          <li><a href="<?= UriFactory::build('{/app}'); ?>">Website</a>
          <li><a href="<?= UriFactory::build('{/app}/components'); ?>">Profile</a>
       </ul>
    </nav>
    <div id="search">
        <input type="text">
    </div>
</header>