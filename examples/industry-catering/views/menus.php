<?php /** @var array $categories @var array $items @var string $basePath */ ?>
<section class="c-page-hero c-page-hero-menu"><div><p class="c-eyebrow c-eyebrow-light">Seasonal, personal, memorable</p><h1>Menus designed<br>to bring people together.</h1><p>Use these selections as a starting point. Every proposal can be shaped around your occasion, guests, and vision.</p></div></section>
<section class="c-wrap c-section">
<div class="c-menu-nav" aria-label="Menu categories"><?php foreach ($categories as $category): ?><a href="#<?= e_attr($category['slug']) ?>"><?= e($category['name']) ?></a><?php endforeach; ?></div>
<?php foreach ($categories as $category): ?>
<section class="c-menu-category" id="<?= e_attr($category['slug']) ?>"><div class="c-menu-category-head"><div><p class="c-eyebrow">Curated selection</p><h2><?= e($category['name']) ?></h2><p><?= e($category['description']) ?></p></div><a class="c-text-link" href="<?= e_attr($basePath) ?>/menus/<?= e_attr($category['slug']) ?>">Full menu <span>→</span></a></div><div class="c-menu-list">
<?php foreach ($items as $item): if ((int) $item['category_id'] !== (int) $category['id']) continue; ?><article><div><h3><?= e($item['name']) ?></h3><p><?= e($item['description']) ?></p><?php if ($item['dietary'] !== ''): ?><div class="c-chips"><?php foreach (explode(',', (string) $item['dietary']) as $tag): ?><span><?= e(trim($tag)) ?></span><?php endforeach; ?></div><?php endif; ?></div><p class="c-menu-price">$<?= e(number_format(((int) $item['price_cents']) / 100, 2)) ?><small><?= e($item['price_unit']) ?></small></p></article><?php endforeach; ?>
</div></section>
<?php endforeach; ?>
<p class="c-disclaimer">Displayed prices are illustrative starting points and are not a final quote. Staffing, rentals, delivery, taxes, service charges, and custom selections may affect pricing.</p>
</section>
