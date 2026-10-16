<?php
/** @var string $content @var array $nav @var string $currentPath @var string $basePath
  * @var \Core\Auth\AdminUser|null $user @var \Core\Security\Csrf $csrf
  * @var string $pageTitle @var string $siteName @var array|null $flash */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle !== '' ? $pageTitle . ' — ' : '') ?><?= e($siteName) ?> Admin</title>
<style>
:root{--bg:#f1f5f9;--side:#0f172a;--sidetext:#cbd5e1;--card:#fff;--text:#0f172a;--muted:#64748b;--accent:#0369a1;--border:#e2e8f0;--ok:#15803d;--bad:#b91c1c}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}
aside{width:14rem;background:var(--side);color:var(--sidetext);padding:1rem;flex-shrink:0}
aside .brand{font-weight:700;color:#fff;margin-bottom:1.5rem;display:block;text-decoration:none}
aside a.nav{display:block;color:var(--sidetext);text-decoration:none;padding:.5rem .75rem;border-radius:.4rem;font-size:.9rem}
aside a.nav:hover{background:#1e293b}
aside a.nav.active{background:var(--accent);color:#fff}
main{flex:1;padding:1.5rem;min-width:0}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem}
h1{font-size:1.25rem;margin:0}
.card{background:var(--card);border:1px solid var(--border);border-radius:.6rem;padding:1.25rem;margin-bottom:1rem}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th,td{text-align:left;padding:.55rem .6rem;border-bottom:1px solid var(--border)}
th{color:var(--muted);font-weight:600;font-size:.8rem;text-transform:uppercase;letter-spacing:.03em}
label{display:block;font-size:.85rem;color:var(--muted);margin:.75rem 0 .25rem}
input,select,textarea{padding:.5rem .65rem;border:1px solid var(--border);border-radius:.4rem;font:inherit;width:100%;max-width:34rem}
textarea{min-height:14rem}
.btn{display:inline-block;background:var(--accent);color:#fff;border:0;border-radius:.4rem;padding:.5rem 1rem;font:inherit;font-size:.88rem;cursor:pointer;text-decoration:none}
.btn:hover{filter:brightness(1.08)}
.btn.secondary{background:#475569}
.btn.danger{background:var(--bad)}
.btn.small{padding:.3rem .6rem;font-size:.8rem}
.flash-ok{background:#dcfce7;border:1px solid #86efac;color:var(--ok);padding:.6rem .9rem;border-radius:.5rem;margin-bottom:1rem}
.flash-error{background:#fee2e2;border:1px solid #fca5a5;color:var(--bad);padding:.6rem .9rem;border-radius:.5rem;margin-bottom:1rem}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;background:#e2e8f0}
.badge.ok{background:#dcfce7;color:var(--ok)} .badge.bad{background:#fee2e2;color:var(--bad)} .badge.warn{background:#fef9c3;color:#854d0e}
.muted{color:var(--muted);font-size:.85rem}
.err{color:var(--bad);font-size:.82rem}
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
@media(max-width:800px){body{flex-direction:column}aside{width:auto}main{padding:1rem}}
</style>
</head>
<body>
<aside>
<a class="brand" href="<?= e_attr($basePath) ?>/admin"><?= e($siteName) ?></a>
<nav aria-label="Admin">
<?php foreach ($nav as $item): ?>
<?php $active = $item['route'] === '/admin'
    ? ($currentPath === '/admin' || $currentPath === '/admin/')
    : str_starts_with($currentPath, $item['route']); ?>
<a class="nav <?= $active ? 'active' : '' ?>"
   href="<?= e_attr($basePath . $item['route']) ?>"><?= e($item['label']) ?></a>
<?php endforeach; ?>
</nav>
</aside>
<main>
<header>
<h1><?= e($pageTitle) ?></h1>
<div>
<span class="muted"><?= e($user?->username ?? '') ?></span>
<a class="btn secondary small" href="<?= e_attr($basePath) ?>/admin/profile">Profile</a>
<form method="post" action="<?= e_attr($basePath) ?>/admin/logout" style="display:inline">
<?= $csrf->field() ?><button class="btn secondary small" type="submit">Sign out</button>
</form>
</div>
</header>
<?php if ($flash): ?>
<div class="flash-<?= e_attr($flash['type'] === 'ok' ? 'ok' : 'error') ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
<?= $content ?>
</main>
</body>
</html>
