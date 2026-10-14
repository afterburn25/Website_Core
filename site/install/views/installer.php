<?php
/** @var array $checks @var array $errors @var array $defaults @var bool $needsToken @var bool $unclaimed @var array $tzList */
$err = fn (string $k) => isset($errors[$k]) ? '<div class="err">' . e($errors[$k]) . '</div>' : '';
$cls = fn (string $k) => isset($errors[$k]) ? ' invalid' : '';
$val = fn (string $k) => e_attr($defaults[$k] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Website Core — Install</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--text:#e2e8f0;--muted:#94a3b8;--accent:#38bdf8;--ok:#4ade80;--bad:#f87171;--border:#334155}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,sans-serif;line-height:1.5}
main{max-width:46rem;margin:2rem auto;padding:0 1rem}
h1{font-size:1.5rem}
h2{font-size:1rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin:2rem 0 .75rem}
.card{background:var(--card);border:1px solid var(--border);border-radius:.75rem;padding:1.25rem;margin-bottom:1rem}
label{display:block;font-size:.85rem;color:var(--muted);margin:.75rem 0 .25rem}
input,select{width:100%;padding:.55rem .7rem;border:1px solid var(--border);border-radius:.4rem;background:#0b1220;color:var(--text);font:inherit}
input.invalid{border-color:var(--bad)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 1rem}
@media(max-width:560px){.grid{grid-template-columns:1fr}}
.checks{list-style:none;margin:0;padding:0;columns:2;font-size:.85rem}
.checks li{padding:.15rem 0}
.ok{color:var(--ok)} .bad{color:var(--bad)}
.err{color:var(--bad);font-size:.8rem;margin-top:.2rem}
.warn{background:#422006;border:1px solid #a16207;border-radius:.5rem;padding:.75rem;font-size:.85rem;margin-bottom:1rem}
button{background:var(--accent);color:#082f49;border:0;border-radius:.5rem;padding:.8rem 2rem;font:inherit;font-weight:600;cursor:pointer;width:100%;margin-top:.5rem}
button:hover{filter:brightness(1.1)}
details{margin-top:1rem}
summary{cursor:pointer;color:var(--muted);font-size:.85rem}
.req{color:var(--accent)}
</style>
</head>
<body>
<main>
<h1>Website Core — Install</h1>
<p style="color:var(--muted)">Everything needed to get running is on this page. One form, one click.</p>

<?php if ($unclaimed): ?>
<div class="warn">
<strong>Claim notice.</strong> No setup token was seeded for this upload, so the first visitor
to this page claims installation. Your browser session has been bound to this setup. For
stronger protection on a public upload, password-protect <code>install.php</code> at your
host panel or seed <code>config/setup.token</code> before visiting — see docs/INSTALLATION.md.
</div>
<?php endif; ?>

<div class="card">
<h2 style="margin-top:0">Environment checks</h2>
<ul class="checks">
<?php foreach ($checks as $c): ?>
<li class="<?= $c['ok'] ? 'ok' : ($c['required'] ? 'bad' : 'warn') ?>">
    <?= $c['ok'] ? '✓' : ($c['required'] ? '✗' : '△') ?> <?= e($c['name']) ?>
    <span style="color:var(--muted)">— <?= e($c['detail']) ?></span>
</li>
<?php endforeach; ?>
</ul>
</div>

<form method="post" action="install.php" novalidate>
<div class="card">
<h2 style="margin-top:0">Site</h2>
<label for="site_name">Site name <span class="req">*</span></label>
<input id="site_name" name="site_name" class="<?= e(trim($cls('site_name'))) ?>" value="<?= $val('site_name') ?>" required>
<?= $err('site_name') ?>
<label for="base_url">Base URL <span class="req">*</span></label>
<input id="base_url" name="base_url" value="<?= $val('base_url') ?>" required>
<?= $err('base_url') ?>
<div class="grid">
<div>
<label for="timezone">Time zone</label>
<select id="timezone" name="timezone">
<?php foreach ($tzList as $tz): ?>
<option value="<?= e_attr($tz) ?>" <?= ($defaults['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label for="storage_path">Private storage path</label>
<input id="storage_path" name="storage_path" value="<?= $val('storage_path') ?>">
<?= $err('storage_path') ?>
</div>
</div>
</div>

<div class="card">
<h2 style="margin-top:0">Database (MySQL / MariaDB)</h2>
<div class="grid">
<div><label for="db_host">Host</label><input id="db_host" name="db_host" value="<?= $val('db_host') ?>"></div>
<div><label for="db_port">Port</label><input id="db_port" name="db_port" value="<?= $val('db_port') ?>"></div>
</div>
<label for="db_name">Database name <span class="req">*</span></label>
<input id="db_name" name="db_name" value="<?= $val('db_name') ?>" required>
<?= $err('db_name') ?>
<div class="grid">
<div><label for="db_user">User <span class="req">*</span></label><input id="db_user" name="db_user" value="<?= $val('db_user') ?>" required></div>
<div><label for="db_pass">Password</label><input id="db_pass" name="db_pass" type="password" autocomplete="off"></div>
</div>
<label for="db_prefix">Table prefix</label>
<input id="db_prefix" name="db_prefix" value="<?= $val('db_prefix') ?>">
<?= $err('db_prefix') ?>
</div>

<div class="card">
<h2 style="margin-top:0">Owner account</h2>
<div class="grid">
<div><label for="owner_username">Username <span class="req">*</span></label>
<input id="owner_username" name="owner_username" value="<?= $val('owner_username') ?>" required>
<?= $err('owner_username') ?></div>
<div><label for="owner_email">Email <span class="req">*</span></label>
<input id="owner_email" name="owner_email" type="email" value="<?= $val('owner_email') ?>" required>
<?= $err('owner_email') ?></div>
</div>
<div class="grid">
<div><label for="owner_password">Password (12+ chars) <span class="req">*</span></label>
<input id="owner_password" name="owner_password" type="password" autocomplete="new-password" required>
<?= $err('owner_password') ?></div>
<div><label for="owner_password2">Confirm password <span class="req">*</span></label>
<input id="owner_password2" name="owner_password2" type="password" autocomplete="new-password" required>
<?= $err('owner_password2') ?></div>
</div>

<?php if ($needsToken): ?>
<label for="setup_token">Setup token <span class="req">*</span></label>
<input id="setup_token" name="setup_token" autocomplete="off">
<?= $err('setup_token') ?>
<?php endif; ?>

<details>
<summary>What happens when I click Install?</summary>
<p style="font-size:.85rem;color:var(--muted)">
The installer verifies the environment, connects to MySQL, creates the core schema,
creates your owner account, writes <code>config/config.php</code> with freshly generated
secrets, marks the site installed, and disables this file. Failed installs can be re-run —
no data is destroyed.
</p>
</details>

<button type="submit">Install</button>
</div>
</form>
</main>
</body>
</html>
