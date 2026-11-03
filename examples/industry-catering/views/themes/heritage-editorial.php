<?php $v=get_defined_vars(); $t=\CajunWebPro\Catering\ThemeSupport::class; ?>
<!doctype html><html lang="en"><head><?= $t::head($v) ?></head><body class="theme-shell theme-heritage-editorial">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="te-masthead theme-nav-shell" data-site-header><?= $t::brand($v) ?><button class="c-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle><span></span><span></span><span></span><span class="sr-only">Open navigation</span></button><nav id="primary-navigation" class="te-nav c-nav" aria-label="Primary" data-nav><?= $t::navigation($navigation,$v) ?></nav><div class="theme-header-action"><?= $t::action($v,'Plan an event','c-button') ?></div></header>
<div class="te-frame"><aside class="te-visual"><img src="<?= $t::media($v,'hero') ?>" alt="A welcoming catered gathering"><p>Food · place · people</p></aside><main id="main-content" class="te-content"><?= $content ?></main></div>
<?= $t::cta($v,'Gather around a table that feels like yours.') ?>
<footer class="te-footer"><div><?= $t::brand($v,'c-brand c-brand-footer') ?><p><?= e($copyrightNotice) ?></p></div><nav aria-label="Footer"><?= $t::navigation($footerNavigation??[],$v,'c-footer-nav') ?></nav><div><a href="tel:<?= e_attr((string)preg_replace('/[^0-9+]/','',$phone)) ?>"><?= e($phone) ?></a><a href="mailto:<?= e_attr($contactEmail) ?>"><?= e($contactEmail) ?></a><?= $t::social($footerSocialLinks??[],'c-footer-social','Social media') ?></div></footer>
<?= $t::scripts($v) ?></body></html>
