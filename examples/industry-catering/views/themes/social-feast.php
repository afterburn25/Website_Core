<?php $v=get_defined_vars(); $t=\CajunWebPro\Catering\ThemeSupport::class; ?>
<!doctype html><html lang="en"><head><?= $t::head($v) ?></head><body class="theme-shell theme-social-feast">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="ts-header theme-nav-shell" data-site-header><?= $t::brand($v) ?><button class="c-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle><span></span><span></span><span></span><span class="sr-only">Open navigation</span></button><nav id="primary-navigation" class="ts-nav c-nav" aria-label="Primary" data-nav><?= $t::navigation($navigation,$v) ?></nav><div class="theme-header-action"><?= $t::social($headerSocialLinks??[],'c-header-social','Social media') ?><?= $t::action($v,'Let’s feast','ts-cta') ?></div></header>
<section class="ts-mosaic" aria-label="Catering inspiration"><figure><img src="<?= $t::heroImage($v) ?>" alt=""><figcaption><?= $t::heroEyebrow($v) ?></figcaption></figure><div><h1><?= $t::heroTitle($v) ?></h1><p><?= $t::heroDescription($v) ?></p></div></section>
<main id="main-content" class="ts-content"><?= $content ?></main>
<footer class="ts-footer"><div><h2>See what we’re serving.</h2><?= $t::social($footerSocialLinks??[],'c-footer-social','Social media') ?></div><nav aria-label="Footer"><?= $t::navigation($footerNavigation??[],$v,'c-footer-nav') ?></nav><p><?= e($copyrightNotice) ?></p></footer>
<?= $t::scripts($v) ?></body></html>
