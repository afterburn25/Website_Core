<?php $v=get_defined_vars(); $t=\CajunWebPro\Catering\ThemeSupport::class; ?>
<!doctype html><html lang="en"><head><?= $t::head($v) ?></head><body class="theme-shell theme-minimal-luxe">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="tl-header theme-nav-shell" data-site-header><?= $t::brand($v) ?><button class="c-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle><span></span><span></span><span></span><span class="sr-only">Open navigation</span></button><nav id="primary-navigation" class="tl-nav c-nav" aria-label="Primary" data-nav><?= $t::navigation($navigation,$v) ?></nav><div class="theme-header-action"><?= $t::action($v,'Inquire','c-button') ?></div></header>
<section class="tl-opening"><p>Catering, considered.</p><h1>Quiet confidence for remarkable gatherings.</h1><figure><img src="<?= $t::media($v,'hero') ?>" alt="Refined catered dessert"><figcaption><?= e($tagline) ?></figcaption></figure></section>
<main id="main-content" class="tl-content"><?= $content ?></main>
<section class="tl-contact"><span>Begin with a conversation.</span><a href="mailto:<?= e_attr($contactEmail) ?>"><?= e($contactEmail) ?></a><a href="tel:<?= e_attr((string)preg_replace('/[^0-9+]/','',$phone)) ?>"><?= e($phone) ?></a></section>
<footer class="tl-footer"><nav aria-label="Footer"><?= $t::navigation($footerNavigation??[],$v,'c-footer-nav') ?></nav><div><?= $t::brand($v,'c-brand c-brand-footer') ?><p><?= e($copyrightNotice) ?></p></div><?= $t::social($footerSocialLinks??[],'c-footer-social','Social media') ?></footer>
<?= $t::scripts($v) ?></body></html>
