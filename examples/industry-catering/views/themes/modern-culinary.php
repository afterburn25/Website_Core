<?php $v=get_defined_vars(); $t=\CajunWebPro\Catering\ThemeSupport::class; ?>
<!doctype html><html lang="en"><head><?= $t::head($v) ?></head><body class="theme-shell theme-modern-culinary">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="tm-header theme-nav-shell" data-site-header><?= $t::brand($v) ?><button class="c-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle><span></span><span></span><span></span><span class="sr-only">Open navigation</span></button><nav id="primary-navigation" class="tm-nav c-nav" aria-label="Primary" data-nav><?= $t::navigation($navigation,$v) ?></nav><div class="theme-header-action"><?= $t::social($headerSocialLinks??[],'c-header-social','Social media') ?><?= $t::action($v,'Plan an event','c-button') ?></div></header>
<section class="tm-intro" aria-label="Catering showcase"><div><span><?= $t::heroEyebrow($v) ?></span><h1><?= $t::heroTitle($v) ?></h1></div><figure><img src="<?= $t::heroImage($v) ?>" alt=""><figcaption><?= $t::heroDescription($v) ?></figcaption></figure><?= $t::action($v,'Start your menu →') ?></section>
<main id="main-content" class="tm-content"><?= $content ?></main>
<footer class="tm-footer"><section><h2>Bring the table to life.</h2><?= $t::action($v,'Request a Quote','c-button') ?></section><nav aria-label="Footer"><?= $t::navigation($footerNavigation??[],$v,'c-footer-nav') ?></nav><p><?= e($copyrightNotice) ?></p><?= $t::social($footerSocialLinks??[],'c-footer-social','Social media') ?></footer>
<?= $t::scripts($v) ?></body></html>
