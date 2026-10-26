<?php /** @var \Core\Security\Csrf $csrf @var ?string $error @var bool $sent @var array $old @var string $basePath */ ?>
<div class="inquiry-form">
<h1>Contact us</h1>
<?php if ($sent): ?>
<p class="inquiry-thanks">Thank you — your inquiry has been received.</p>
<?php else: ?>
<?php if ($error): ?><p class="inquiry-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e_attr($basePath) ?>/inquiry">
<?= $csrf->field() ?>
<p><label for="inq-name">Name</label><br>
<input id="inq-name" name="name" value="<?= e_attr($old['name'] ?? '') ?>" required maxlength="190"></p>
<p><label for="inq-email">Email</label><br>
<input id="inq-email" name="email" type="email" value="<?= e_attr($old['email'] ?? '') ?>" required></p>
<p><label for="inq-message">Message</label><br>
<textarea id="inq-message" name="message" rows="6" required maxlength="5000"><?= e($old['message'] ?? '') ?></textarea></p>
<p style="position:absolute;left:-9999px" aria-hidden="true">
<label>Website <input name="website" tabindex="-1" autocomplete="off"></label>
</p>
<p><button type="submit">Send inquiry</button></p>
</form>
<?php endif; ?>
</div>
