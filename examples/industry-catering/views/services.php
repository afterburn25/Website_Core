<?php /** @var array $services @var string $basePath */ ?>
<section class="c-page-hero c-page-hero-services"><div><p class="c-eyebrow c-eyebrow-light">Made for every gathering</p><h1>Catering experiences,<br>beautifully considered.</h1><p>From milestone celebrations to business gatherings, choose a service designed around the way you want to host.</p></div></section>
<section class="c-wrap c-section"><div class="c-service-list">
<?php $i = 0; foreach ($services as $slug => $service): $i++; ?>
<article class="c-service-row c-reveal<?= $i % 2 === 0 ? ' is-reversed' : '' ?>"><div class="c-service-row-photo c-photo-<?= e_attr(str_replace(['-events', '-celebrations', '-catering'], '', $slug)) ?>"></div><div class="c-service-row-copy"><span class="c-index">0<?= $i ?></span><p class="c-eyebrow"><?= e($service['kicker']) ?></p><h2><?= e($service['title']) ?></h2><p><?= e($service['summary']) ?></p><a class="c-text-link" href="<?= e_attr($basePath) ?>/services/<?= e_attr($slug) ?>">Discover this service <span>→</span></a></div></article>
<?php endforeach; ?>
</div></section>
