<?php $v=get_defined_vars(); $t=\CajunWebPro\Catering\ThemeSupport::class; ?>
<!doctype html><html lang="en"><head><?= $t::head($v) ?></head><body class="theme-shell theme-rustic-gathering">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="tr-header theme-nav-shell" data-site-header><div class="tr-brand"><?= $t::brand($v) ?><em>Made for gathering</em></div><button class="c-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle><span></span><span></span><span></span><span class="sr-only">Open navigation</span></button><nav id="primary-navigation" class="tr-nav c-nav" aria-label="Primary" data-nav><?= $t::navigation($navigation,$v) ?></nav><div class="theme-header-action"><?= $t::social($headerSocialLinks??[],'c-header-social','Social media') ?><?= $t::action($v,'Save your date','c-button') ?></div></header>
<section class="tr-collage"><img src="<?= $t::heroImage($v) ?>" alt=""><div><p><?= $t::heroEyebrow($v) ?></p><h1><?= $t::heroTitle($v) ?></h1></div></section>
<main id="main-content" class="tr-content"><div class="tr-paper"><?= $content ?></div></main>
<?= $t::cta($v,'Let’s make room for everyone at the table.') ?>
<footer class="tr-footer"><div><?= $t::brand($v,'c-brand c-brand-footer') ?><p><?= e($tagline) ?></p></div><nav aria-label="Footer"><?= $t::navigation($footerNavigation??[],$v,'c-footer-nav') ?></nav><div><a href="mailto:<?= e_attr($contactEmail) ?>"><?= e($contactEmail) ?></a><p><?= e($copyrightNotice) ?></p></div><?= $t::social($footerSocialLinks??[],'c-footer-social','Social media') ?></footer>
<?= $t::scripts($v) ?></body></html>
