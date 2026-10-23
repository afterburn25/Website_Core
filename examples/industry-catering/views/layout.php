<?php
/** @var string $content @var string $siteName @var string $tagline @var string $basePath
 * @var string $currentPath @var string $phone @var string $contactEmail @var array $page */
$asset = $basePath . '/_a/modules/catering';
$nav = [
    ['Our Story', '/about'], ['Menus', '/menus'], ['Services', '/services'],
    ['Packages', '/packages'], ['Gallery', '/gallery'], ['Contact', '/contact'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(($page['title'] ?? '') !== 'Home' ? ($page['title'] ?? '') . ' — ' : '') ?><?= e($siteName) ?></title>
<meta name="description" content="<?= e_attr($page['meta_description'] ?? '') ?>">
<?php if (!empty($page['noindex'])): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<link rel="stylesheet" href="<?= e_attr($asset) ?>/assets/css/catering.css">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="c-site-header" data-site-header>
<a class="c-brand" href="<?= e_attr($basePath) ?>/" aria-label="<?= e_attr($siteName) ?> home">
<span class="c-brand-name"><?= e($siteName) ?></span>
<span class="c-brand-rule"></span>
<span class="c-brand-kind">Catering</span>
</a>
<button class="c-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle>
<span></span><span></span><span></span><span class="sr-only">Open navigation</span>
</button>
<nav id="primary-navigation" class="c-nav" aria-label="Primary" data-nav>
<?php foreach ($nav as [$label, $url]): ?>
<?php $active = $currentPath === $url || ($url !== '/about' && str_starts_with($currentPath, $url . '/')); ?>
<a href="<?= e_attr($basePath . $url) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
</nav>
<a class="c-button c-button-gold c-header-cta" href="<?= e_attr($basePath) ?>/request-a-quote">Request a Quote</a>
</header>
<main id="main-content">
<?= $content ?>
</main>
<section class="c-final-cta" aria-labelledby="final-cta-title">
<div>
<p class="c-eyebrow c-eyebrow-light">Your gathering starts here</p>
<h2 id="final-cta-title">Let’s create something beautiful together.</h2>
<p>Share a few details and we’ll help shape the menu, service, and experience.</p>
<a class="c-button c-button-gold" href="<?= e_attr($basePath) ?>/request-a-quote">Request a Quote</a>
</div>
</section>
<footer class="c-footer">
<div class="c-footer-grid">
<div>
<a class="c-brand c-brand-footer" href="<?= e_attr($basePath) ?>/">
<span class="c-brand-name"><?= e($siteName) ?></span><span class="c-brand-kind">Catering</span>
</a>
<p><?= e($tagline) ?> Thoughtful food and gracious hospitality for weddings, corporate events, and private celebrations.</p>
</div>
<div><h2>Explore</h2><a href="<?= e_attr($basePath) ?>/menus">Menus</a><a href="<?= e_attr($basePath) ?>/services">Services</a><a href="<?= e_attr($basePath) ?>/packages">Packages</a><a href="<?= e_attr($basePath) ?>/gallery">Gallery</a></div>
<div><h2>Plan</h2><a href="<?= e_attr($basePath) ?>/request-a-quote">Request a Quote</a><a href="<?= e_attr($basePath) ?>/service-area">Service Area</a><a href="<?= e_attr($basePath) ?>/faq">FAQ</a><a href="<?= e_attr($basePath) ?>/contact">Contact</a></div>
<div><h2>Get in touch</h2><a href="tel:<?= e_attr((string) preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a><a href="mailto:<?= e_attr($contactEmail) ?>"><?= e($contactEmail) ?></a><p>Consultations by appointment.</p></div>
</div>
<div class="c-footer-bottom"><span>© <?= date('Y') ?> <?= e($siteName) ?></span><span>Demo content should be replaced before launch.</span></div>
</footer>
<script src="<?= e_attr($asset) ?>/assets/js/catering.js" defer></script>
</body>
</html>
