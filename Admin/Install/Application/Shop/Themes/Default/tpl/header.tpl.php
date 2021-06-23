<?php
/**
 * Orange Management
 *
 * PHP Version 8.0
 *
 * @package   Web\{APPNAME}
 * @copyright Dennis Eichhorn
 * @license   OMS License 1.0
 * @version   1.0.0
 * @link      https://orange-management.org
 */
declare(strict_types=1);

use phpOMS\Uri\UriFactory;

?>
<nav>
   <ul>
      <li><a href="<?= UriFactory::build('{/app}'); ?>">Main</a>
      <li>|
      <li><a href="<?= UriFactory::build('{/app}/components'); ?>">Components</a>
   </ul>
</nav>
