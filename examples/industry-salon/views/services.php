<?php /** @var array $services @var array $staff */ ?>
<div class="salon-services">
<h1>Services</h1>
<?php if ($services === []): ?>
<p>Our service list is being updated — check back soon.</p>
<?php endif; ?>
<?php foreach ($services as $s): ?>
<div class="salon-service">
<span class="salon-service-name"><?= e($s['name']) ?></span>
<span class="salon-service-meta">
<?= (int) $s['duration_minutes'] ?> min · $<?= e(number_format(((int) $s['price_cents']) / 100, 2)) ?>
</span>
<?php if (!empty($s['description'])): ?><p><?= e($s['description']) ?></p><?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($staff !== []): ?>
<h2>Our team</h2>
<?php foreach ($staff as $person): ?>
<div class="salon-staff">
<strong><?= e($person['name']) ?></strong>
<?php if (!empty($person['title'])): ?> — <?= e($person['title']) ?><?php endif; ?>
<?php if (!empty($person['bio'])): ?><p><?= e($person['bio']) ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
