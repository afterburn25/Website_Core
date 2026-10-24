<?php /** @var string|null $error @var string $token @var bool $done @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Choose new password — Website Core</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;font-family:system-ui;color:#e2e8f0}
.card{background:#1e293b;border:1px solid #334155;border-radius:.75rem;padding:2rem;width:100%;max-width:22rem}
h1{font-size:1.2rem;margin:0 0 1.25rem}
label{display:block;font-size:.85rem;color:#94a3b8;margin:.75rem 0 .25rem}
input{width:100%;padding:.55rem .7rem;border:1px solid #334155;border-radius:.4rem;background:#0b1220;color:#e2e8f0;font:inherit;box-sizing:border-box}
button{width:100%;margin-top:1.25rem;background:#38bdf8;color:#082f49;border:0;border-radius:.4rem;padding:.65rem;font:inherit;font-weight:600;cursor:pointer}
.error{background:#450a0a;border:1px solid #b91c1c;color:#fecaca;padding:.6rem .8rem;border-radius:.4rem;font-size:.85rem;margin-bottom:.5rem}
.note{background:#052e16;border:1px solid #166534;color:#bbf7d0;padding:.6rem .8rem;border-radius:.4rem;font-size:.85rem;margin-bottom:.5rem}
a{color:#38bdf8;font-size:.85rem;display:inline-block;margin-top:1rem}
</style>
</head>
<body>
<div class="card">
<h1>Choose a new password</h1>
<?php if (!empty($done)): ?>
<div class="note">Password updated. Sign in with your new password.</div>
<a href="<?= e_attr($basePath) ?>/admin/login">Sign in</a>
<?php else: ?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/reset">
<?= $csrf->field() ?>
<input type="hidden" name="token" value="<?= e_attr($token ?? '') ?>">
<label for="password">New password (min 12 characters)</label>
<input id="password" name="password" type="password" autocomplete="new-password" required autofocus>
<label for="password2">Repeat new password</label>
<input id="password2" name="password2" type="password" autocomplete="new-password" required>
<button type="submit">Update password</button>
</form>
<a href="<?= e_attr($basePath) ?>/admin/login">Back to sign in</a>
<?php endif; ?>
</div>
</body>
</html>
