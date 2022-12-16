<?php
/**
 * Karaka
 *
 * PHP Version 8.1
 *
 * @package   Web\{APPNAME}
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://jingga.app
 */
declare(strict_types=1);

use phpOMS\Uri\UriFactory;

?>
<header>
    <nav>
       <ul>
          <li><a href="<?= UriFactory::build('{/lang}/{/app}/{/app}'); ?>">Website</a>
          <li><a href="<?= UriFactory::build('{/lang}/{/app}/{/app}/components'); ?>">Profile</a>
       </ul>
    </nav>
    <div id="search">
        <input type="text" name="search-bar">
    </div>
</header>