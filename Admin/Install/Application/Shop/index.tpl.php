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
 *
 * @link      https://orange-management.org
 */
declare(strict_types=1);

use phpOMS\Uri\UriFactory;

/** @var phpOMS\Model\Html\Head $head */
$head = $this->getData('head');

/** @var array $dispatch */
$dispatch = $this->getData('dispatch') ?? [];
?>
<!DOCTYPE HTML>
<html lang="<?= $this->printHtml($this->response->getLanguage()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#262626">
    <meta name="msapplication-navbutton-color" content="#262626">
    <meta name="apple-mobile-web-app-status-bar-style" content="#262626">
    <meta name="description" content="<?= $this->getHtml(':meta', '0', '0'); ?>">
    <?= $head->render(); ?>

    <base href="<?= UriFactory::build('{/base}'); ?>/">

    <link rel="manifest" href="<?= UriFactory::build('Web/{APPNAME}/manifest.json'); ?>">
    <link rel="shortcut icon" href="<?= UriFactory::build('Web/{APPNAME}/img/favicon.ico'); ?>" type="image/x-icon">

    <title><?= $this->printHtml($head->title); ?></title>

    <?= $head->renderAssets(); ?>

    <style><?= $head->renderStyle(); ?></style>
    <script><?= $head->renderScript(); ?></script>
</head>
<body>
<?php include __DIR__ . '/tpl/header.tpl.php'; ?>
<main>
<?php
foreach ($dispatch as $view) {
    if ($view instanceof \phpOMS\Contract\RenderableInterface) {
        echo $view->render();
    }
}
?>
</main>
<?php include __DIR__ . '/tpl/footer.tpl.php'; ?>
