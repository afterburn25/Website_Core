<?php /** @var array $cats @var array $items */ ?>
<div class="restaurant-menu">
<h1>Menu</h1>
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
