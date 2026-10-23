<?php
/** @var string $content @var array $nav @var string $currentPath @var string $basePath
  * @var \Core\Auth\AdminUser|null $user @var \Core\Security\Csrf $csrf
  * @var string $pageTitle @var string $siteName @var string $coreVersion
  * @var list<array{name:string,version:string}> $industryVersions @var array|null $flash */
$navGroups = [];
$activeRoute = null;
$activeLength = -1;
foreach ($nav as $item) {
    $key = (string) $item['group_key'];
    $navGroups[$key] ??= ['label' => (string) $item['group'], 'items' => []];
    $navGroups[$key]['items'][] = $item;
    $route = rtrim((string) $item['route'], '/');
    $matches = $route === '/admin'
        ? ($currentPath === '/admin' || $currentPath === '/admin/')
        : ($currentPath === $route || str_starts_with($currentPath, $route . '/'));
    if ($matches && strlen($route) > $activeLength) {
        $activeRoute = (string) $item['route'];
        $activeLength = strlen($route);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle !== '' ? $pageTitle . ' — ' : '') ?><?= e($siteName) ?> Admin</title>
<style>
:root{--bg:#eef3f8;--side:#091421;--side2:#101f31;--sidetext:#cbd5e1;--card:#fff;--text:#0f172a;--muted:#64748b;--accent:#0876b9;--accent2:#0ea5e9;--gold:#d7ae57;--border:#dbe5ef;--ok:#15803d;--bad:#b91c1c}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}
aside{width:17rem;height:100vh;position:sticky;top:0;overflow:auto;background:linear-gradient(180deg,var(--side2),var(--side));color:var(--sidetext);padding:1rem;flex-shrink:0;border-right:1px solid #ffffff10;box-shadow:12px 0 36px #0f172a12;display:flex;flex-direction:column}
aside .brand{color:#fff;margin-bottom:1.15rem;display:block;text-decoration:none;padding:.85rem .9rem;border:1px solid #ffffff14;border-radius:.8rem;background:#ffffff08}
.brand-name{display:block;font-family:Georgia,serif;font-weight:700;font-size:1.08rem}.brand-meta{display:block;color:#8ea3bb;font-size:.68rem;letter-spacing:.14em;text-transform:uppercase;margin-top:.2rem}
.nav-group{margin:.38rem 0;border:1px solid transparent;border-radius:.7rem}.nav-group[open]{background:#ffffff05;border-color:#ffffff0d;padding-bottom:.32rem}
.nav-group summary{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.68rem .75rem;color:#91a7be;font-size:.69rem;font-weight:800;letter-spacing:.11em;text-transform:uppercase;cursor:pointer;border-radius:.65rem;user-select:none}
.nav-group summary::-webkit-details-marker{display:none}.nav-group summary:hover{color:#fff;background:#ffffff08}.nav-group summary:focus-visible{outline:2px solid var(--gold);outline-offset:2px}
.nav-group .chevron{width:.72rem;height:.72rem;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:transform .18s ease;margin-top:-.2rem}.nav-group[open] .chevron{transform:rotate(225deg);margin-top:.3rem}
aside a.nav{position:relative;display:flex;align-items:center;gap:.6rem;color:var(--sidetext);text-decoration:none;padding:.55rem .75rem .55rem 1rem;margin:.08rem .34rem;border-radius:.5rem;font-size:.88rem}
aside a.nav:before{content:"";width:.35rem;height:.35rem;border-radius:50%;background:#52677d;flex:0 0 auto}aside a.nav:hover{background:#ffffff0b;color:#fff}aside a.nav:hover:before{background:var(--accent2)}
aside a.nav.active{background:linear-gradient(90deg,#0876b9,#0a659c);color:#fff;box-shadow:0 8px 20px #001f3566}aside a.nav.active:before{background:#fff;box-shadow:0 0 0 4px #ffffff1f}
.admin-nav{flex:1}.system-meta{margin-top:1rem;padding:.8rem .9rem;border-top:1px solid #ffffff12;color:#8ea3bb;font-size:.71rem;line-height:1.5}.system-meta strong{color:#d8e5f2;font-weight:700}.version-row{display:flex;justify-content:space-between;gap:.7rem}.version-row span:last-child{font-variant-numeric:tabular-nums;color:#afc3d7;text-align:right}
main{flex:1;padding:1.5rem;min-width:0}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--border)}
.header-actions{display:flex;align-items:center;justify-content:flex-end;gap:.4rem;flex-wrap:wrap}
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
@media(max-width:800px){body{flex-direction:column}aside{position:relative;width:100%;height:auto;max-height:none}main{padding:1rem}header{align-items:flex-start;gap:.75rem;flex-direction:column}.header-actions{justify-content:flex-start}}
</style>
</head>
<body>
<aside>
<a class="brand" href="<?= e_attr($basePath) ?>/admin"><span class="brand-name"><?= e($siteName) ?></span><span class="brand-meta">Administration</span></a>
<nav class="admin-nav" aria-label="Admin">
<?php foreach ($navGroups as $groupKey => $group): ?>
<?php $groupActive = array_filter($group['items'], fn (array $item): bool => $item['route'] === $activeRoute) !== []; ?>
<details class="nav-group" data-nav-group="<?= e_attr($groupKey) ?>"<?= $groupActive ? ' data-active="true"' : '' ?> open>
<summary><span><?= e($group['label']) ?></span><span class="chevron" aria-hidden="true"></span></summary>
<?php foreach ($group['items'] as $item): ?>
<?php $active = $item['route'] === $activeRoute; ?>
<a class="nav <?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>
   href="<?= e_attr($basePath . $item['route']) ?>"><?= e($item['label']) ?></a>
<?php endforeach; ?>
</details>
<?php endforeach; ?>
</nav>
<div class="system-meta" aria-label="Installed versions">
<div class="version-row"><strong>Website Core</strong><span><?= e($coreVersion) ?></span></div>
<?php foreach ($industryVersions as $industry): ?>
<div class="version-row"><strong><?= e($industry['name']) ?></strong><span><?= e($industry['version']) ?></span></div>
<?php endforeach; ?>
</div>
</aside>
<main>
<header>
<h1><?= e($pageTitle) ?></h1>
<div class="header-actions">
<a class="btn small" href="<?= e_attr(rtrim($basePath, '/') . '/') ?>" target="_blank" rel="noopener">Open site ↗</a>
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
<script>
(() => {
  const stateKey = 'wc-admin-nav-state-v1';
  const scrollKey = 'wc-admin-nav-scroll-v1';
  const sidebar = document.querySelector('aside');
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(stateKey) || '{}') || {}; } catch (_) {}
  document.querySelectorAll('[data-nav-group]').forEach((group) => {
    const key = group.dataset.navGroup;
    if (Object.prototype.hasOwnProperty.call(saved, key)) group.open = Boolean(saved[key]);
    group.addEventListener('toggle', () => {
      saved[key] = group.open;
      try { localStorage.setItem(stateKey, JSON.stringify(saved)); } catch (_) {}
    });
  });
  if (sidebar) {
    try { sidebar.scrollTop = Number(localStorage.getItem(scrollKey) || 0); } catch (_) {}
    let timer;
    sidebar.addEventListener('scroll', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        try { localStorage.setItem(scrollKey, String(sidebar.scrollTop)); } catch (_) {}
      }, 80);
    }, {passive:true});
  }
})();
</script>
</body>
</html>
