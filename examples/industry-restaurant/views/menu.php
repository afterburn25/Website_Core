<?php /** @var array $cats @var array $items @var array $specials */ ?>
<div class="restaurant-menu">
<h1>Menu</h1>
<?php if (!empty($specials)): ?>
<section class="menu-specials">
<h2>Today's Specials</h2>
<?php foreach ($specials as $s): ?>
<div class="menu-item">
<span class="menu-item-name"><?= e($s['fields']['dish'] ?? '') ?></span>
<?php if (($s['fields']['price'] ?? '') !== ''): ?>
<span class="menu-item-price">$<?= e(number_format((float) $s['fields']['price'], 2)) ?></span>
<?php endif; ?>
<?php if (($s['fields']['description'] ?? '') !== ''): ?>
<p class="menu-item-desc"><?= e($s['fields']['description']) ?></p>
<?php endif; ?>
</div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($cats === []): ?>
<p>Our menu is being updated — check back soon.</p>
<?php endif; ?>
<?php foreach ($cats as $cat): ?>
<section class="menu-category">
<h2><?= e($cat['name']) ?></h2>
<?php foreach ($items as $item): if ((int) $item['category_id'] !== (int) $cat['id']) continue; ?>
<div class="menu-item">
<span class="menu-item-name"><?= e($item['name']) ?></span>
<span class="menu-item-price"><?= e('$' . number_format(((int) $item['price_cents']) / 100, 2)) ?></span>
<?php if (!empty($item['description'])): ?>
<p class="menu-item-desc"><?= e($item['description']) ?></p>
<?php endif; ?>
</div>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
</div>
