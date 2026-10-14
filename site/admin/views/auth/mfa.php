<?php /** @var string|null $error @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verification — Website Core</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;font-family:system-ui;color:#e2e8f0}
.card{background:#1e293b;border:1px solid #334155;border-radius:.75rem;padding:2rem;width:100%;max-width:22rem}
h1{font-size:1.2rem;margin:0 0 .5rem}
p{color:#94a3b8;font-size:.85rem}
input{width:100%;padding:.6rem .7rem;border:1px solid #334155;border-radius:.4rem;background:#0b1220;color:#e2e8f0;font:inherit;font-size:1.2rem;text-align:center;letter-spacing:.3em;box-sizing:border-box}
button{width:100%;margin-top:1.25rem;background:#38bdf8;color:#082f49;border:0;border-radius:.4rem;padding:.65rem;font:inherit;font-weight:600;cursor:pointer}
.error{background:#450a0a;border:1px solid #b91c1c;color:#fecaca;padding:.6rem .8rem;border-radius:.4rem;font-size:.85rem;margin-bottom:.5rem}
</style>
</head>
<body>
<div class="card">
<h1>Two-factor verification</h1>
<p>Enter the 6-digit code from your authenticator, or a recovery code.</p>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/mfa">
<?= $csrf->field() ?>
<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required>
<button type="submit">Verify</button>
</form>
</div>
</body>
</html>
