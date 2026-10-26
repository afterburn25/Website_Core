<div class="page-head"><div><p class="section-kicker">Location and range</p><h1>Geo service area</h1><p>Choose the city and state at the center of the service area, then set the range from that location in miles.</p></div><?php if (!empty($serviceAreaState['configured'])): ?><span class="badge ok">Verified</span><?php else: ?><span class="badge warn">Not verified</span><?php endif; ?></div>
<?php if($errors): ?><div class="alert error" role="alert"><strong>The service area could not be verified.</strong><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/service-area" class="settings-form">
<?= $csrf->field() ?>
<section class="card">
<div class="card-heading"><div><h2>Center and range</h2><p class="card-subtitle">Core verifies the center on the server. Enabled modules can use the saved range to display a map and validate customer addresses.</p></div></div>
<div class="form-grid">
<label>City<input name="service_area_center_city" autocomplete="address-level2" maxlength="500" value="<?= e_attr((string)($profile['service_area_center_city']??'')) ?>" required></label>
<label>State / region<input name="service_area_center_region" autocomplete="address-level1" maxlength="500" value="<?= e_attr((string)($profile['service_area_center_region']??'')) ?>" required></label>
<label>Country <small>optional</small><input name="service_area_center_country" autocomplete="country-name" maxlength="500" value="<?= e_attr((string)($profile['service_area_center_country']??'')) ?>"></label>
<label>Range from city (miles)<input type="number" name="service_area_radius_miles" min="1" max="500" step="0.1" inputmode="decimal" value="<?= e_attr((string)($profile['service_area_radius_miles']??'')) ?>" required></label>
</div>
<?php if (!empty($serviceAreaProviders)): ?><label>Geocoding provider<select name="service_area_provider"><?php foreach ($serviceAreaProviders as $provider=>$attribution): ?><option value="<?= e_attr($provider) ?>" <?= ($serviceAreaState['provider_key']??'')===$provider?'selected':'' ?>><?= e($attribution) ?></option><?php endforeach; ?></select></label><?php else: ?><div class="alert warn">The enabled module has not registered a server-side geocoding provider. Install or enable its provider before saving this service area.</div><?php endif; ?>
<?php if (!empty($serviceAreaState['configured'])): ?><p class="field-note">Verified as <?= e((string)$serviceAreaState['normalized']) ?> · <?= e((string)$serviceAreaState['radius_miles']) ?>-mile range · <?= e((string)$serviceAreaState['precision']) ?> precision</p><?php endif; ?>
<div class="action-bar"><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/business">Back to business profile</a><button class="btn" type="submit"<?= empty($serviceAreaProviders)?' disabled':'' ?>>Save and verify service area</button></div>
</section>
</form>
