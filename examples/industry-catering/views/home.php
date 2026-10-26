<?php /** @var array $featuredItems @var array $packages @var string $basePath @var string $assetBase */ ?>
<section class="c-hero c-hero-home">
<div class="c-hero-shade"></div>
<div class="c-hero-content">
<p class="c-eyebrow c-eyebrow-light">Exceptional catering for every occasion</p>
<h1>Gather<br>beautifully.</h1>
<p class="c-hero-copy">Exceptional food. Unforgettable moments. We create remarkable dining experiences for life’s most meaningful occasions.</p>
<div class="c-actions"><a class="c-button c-button-gold" href="<?= e_attr($basePath) ?>/request-a-quote">Request a Quote</a><a class="c-text-link c-text-link-light" href="<?= e_attr($basePath) ?>/menus">Explore our menus <span>→</span></a></div>
</div>
<a class="c-scroll-cue" href="#story"><span>Discover</span><i></i></a>
</section>

<section class="c-story-split c-section" id="story">
<div class="c-story-copy c-reveal">
<p class="c-eyebrow">Food with intention</p>
<h2>Thoughtful menus.<br>Meaningful moments.</h2>
<p class="c-lead">From intimate gatherings to grand celebrations, seasonal menus and impeccable service turn your vision into an effortless experience.</p>
<a class="c-text-link" href="<?= e_attr($basePath) ?>/about">Our story <span>→</span></a>
</div>
<div class="c-photo c-photo-plated c-reveal" role="img" aria-label="A refined plated entrée"></div>
</section>

<section class="c-service-cards c-wrap c-section" aria-labelledby="occasions-title">
<div class="c-section-heading"><div><p class="c-eyebrow">Made for the occasion</p><h2 id="occasions-title">How will you gather?</h2></div><a class="c-text-link" href="<?= e_attr($basePath) ?>/services">View all services <span>→</span></a></div>
<div class="c-card-grid c-card-grid-three">
<a class="c-image-card c-reveal" href="<?= e_attr($basePath) ?>/services/weddings"><div class="c-card-image c-photo-wedding"></div><div class="c-card-body"><h3>Weddings</h3><p>Romantic, refined, and completely personalized for your day.</p><span>Explore weddings →</span></div></a>
<a class="c-image-card c-reveal" href="<?= e_attr($basePath) ?>/services/corporate-events"><div class="c-card-image c-photo-corporate"></div><div class="c-card-body"><h3>Corporate Events</h3><p>Elevated dining for conferences, client events, and team celebrations.</p><span>Explore corporate →</span></div></a>
<a class="c-image-card c-reveal" href="<?= e_attr($basePath) ?>/services/private-celebrations"><div class="c-card-image c-photo-private"></div><div class="c-card-body"><h3>Private Celebrations</h3><p>Extraordinary food for life’s meaningful moments, big and small.</p><span>Explore celebrations →</span></div></a>
</div>
</section>

<section class="c-menu-feature c-wrap c-section" aria-labelledby="signature-menu-title">
<div class="c-section-heading"><div><p class="c-eyebrow">A taste of what’s possible</p><h2 id="signature-menu-title">Signature selections</h2><p>Seasonal ingredients, inspired cuisine, beautifully presented.</p></div><a class="c-button c-button-green" href="<?= e_attr($basePath) ?>/menus">Explore Our Menus</a></div>
<div class="c-card-grid c-card-grid-three">
<?php foreach ($featuredItems as $index => $item): ?>
<article class="c-dish-card c-reveal"><?php if (!empty($item['image'])): ?><img class="c-dish-photo" src="<?= e_attr($assetBase . '/assets/images/menu/' . rawurlencode((string) $item['image'])) ?>" alt="<?= e_attr($item['name']) ?>" loading="lazy" width="1200" height="800"><?php else: ?><div class="c-dish-photo c-dish-photo-<?= $index + 1 ?>" role="img" aria-label="<?= e_attr($item['name']) ?>"></div><?php endif; ?><div class="c-card-body">
<?php if ($item['dietary'] !== ''): ?><div class="c-chips"><?php foreach (explode(',', (string) $item['dietary']) as $tag): ?><span><?= e(trim($tag)) ?></span><?php endforeach; ?></div><?php endif; ?>
<h3><?= e($item['name']) ?></h3><p><?= e($item['description']) ?></p><div class="c-price">From $<?= e(number_format(((int) $item['price_cents']) / 100, 2)) ?> <?= e($item['price_unit']) ?></div>
</div></article>
<?php endforeach; ?>
</div>
</section>

<section class="c-experience-banner">
<div class="c-experience-copy"><p class="c-eyebrow c-eyebrow-light">More than a meal</p><h2>Experiences<br>people remember.</h2><p>From breathtaking venues to intimate backyards, hospitality is in every detail.</p><a class="c-text-link c-text-link-light" href="<?= e_attr($basePath) ?>/gallery">View gallery <span>→</span></a></div>
</section>

<section class="c-packages c-wrap c-section" aria-labelledby="packages-title">
<div class="c-heading-centered"><p class="c-eyebrow">Catering packages</p><h2 id="packages-title">Designed around you</h2><p>Thoughtfully curated starting points, with the flexibility to make each one yours.</p></div>
<div class="c-card-grid c-card-grid-three">
<?php foreach ($packages as $package): ?>
<article class="c-package-card c-reveal<?= $package['featured'] ? ' is-featured' : '' ?>">
<?php if ($package['featured']): ?><span class="c-package-ribbon">Most popular</span><?php endif; ?>
<p class="c-eyebrow"><?= e($package['eyebrow']) ?></p><h3><?= e($package['name']) ?></h3><p><?= e($package['description']) ?></p>
<p class="c-package-price"><strong>From $<?= e(number_format(((int) $package['price_cents']) / 100, 0)) ?></strong> <?= e($package['price_note']) ?></p>
<a class="c-text-link" href="<?= e_attr($basePath) ?>/packages/<?= e_attr($package['slug']) ?>">View package <span>→</span></a>
</article>
<?php endforeach; ?>
</div>
</section>

<section class="c-testimonial">
<div class="c-testimonial-photo"></div><div class="c-testimonial-copy"><p class="c-eyebrow c-eyebrow-light">Kind words</p><h2>From our clients</h2><blockquote>“Add an authentic customer story here—one that reflects the food, service, and feeling of the day.”</blockquote><p class="c-quote-credit">Sample testimonial · Replace before launch</p></div>
</section>

<section class="c-process c-wrap c-section" aria-labelledby="process-title">
<div class="c-heading-centered"><p class="c-eyebrow">From vision to table</p><h2 id="process-title">A simple process</h2></div>
<ol><li><span>1</span><div><h3>Share your vision</h3><p>Tell us about your event, guests, style, and what matters most.</p></div></li><li><span>2</span><div><h3>Plan together</h3><p>We shape a personalized menu and service approach around you.</p></div></li><li><span>3</span><div><h3>Celebrate</h3><p>We handle the details so you can focus on what matters.</p></div></li></ol>
</section>
