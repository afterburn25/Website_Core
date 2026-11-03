<?php $v=get_defined_vars(); $t=\CajunWebPro\Catering\ThemeSupport::class; ?>
<!doctype html><html lang="en"><head><?= $t::head($v) ?></head><body class="theme-shell theme-grand-wedding">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="tw-bar theme-nav-shell" data-site-header><?= $t::brand($v) ?><button class="c-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle><span></span><span></span><span></span><span class="sr-only">Open navigation</span></button><nav id="primary-navigation" class="tw-nav c-nav" aria-label="Primary" data-nav><?= $t::navigation($navigation,$v) ?></nav><div class="theme-header-action"><?= $t::action($v,'Plan the day','c-button') ?></div></header>
<section class="tw-hero" style="background-image:linear-gradient(180deg,rgba(10,10,10,.18),rgba(10,10,10,.72)),url('<?= $t::media($v,'hero') ?>')"><div class="tw-title"><p>Once-in-a-lifetime hospitality</p><h1>Every detail, beautifully composed.</h1><?= $t::action($v,'Design your celebration','c-button c-button-gold') ?></div></section>
<main id="main-content" class="tw-content"><div class="tw-monogram" aria-hidden="true">✦</div><?= $content ?></main>
<?= $t::cta($v,'Your celebration deserves a table to remember.') ?>
<footer class="tw-footer"><nav aria-label="Footer"><?= $t::navigation($footerNavigation??[],$v,'c-footer-nav') ?></nav><?= $t::brand($v,'c-brand c-brand-footer') ?><div><a href="mailto:<?= e_attr($contactEmail) ?>"><?= e($contactEmail) ?></a><p><?= e($copyrightNotice) ?></p></div></footer>
<?= $t::scripts($v) ?></body></html>
