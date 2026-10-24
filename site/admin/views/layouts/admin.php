<?php
/** @var string $content @var array $nav @var string $currentPath @var string $basePath @var string $siteBasePath
  * @var \Core\Auth\AdminUser|null $user @var \Core\Security\Csrf $csrf
  * @var string $pageTitle @var string $siteName @var string $coreVersion @var string|null $brandLogoUrl @var string|null $faviconUrl
  * @var list<array{name:string,version:string}> $industryVersions @var int $noticeCount @var array|null $flash */
$navGroups = [];
$activeRoute = null;
$activeLength = -1;
$activeGroup = 'Administration';
$noticeCount = (int) ($noticeCount ?? 0);
$assetVersion = rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development');
$username = trim((string) ($user?->username ?? 'Administrator'));
$userInitial = mb_substr($username !== '' ? $username : 'A', 0, 1);
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
        $activeGroup = (string) $item['group'];
        $activeLength = strlen($route);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle !== '' ? $pageTitle . ' — ' : '') ?><?= e($siteName) ?> Admin</title>
<?php if (!empty($faviconUrl)): ?><link rel="icon" href="<?= e_attr($faviconUrl) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e_attr($basePath) ?>/_a/core/css/admin.css&amp;v=<?= e_attr($assetVersion) ?>">
</head>
<body>
<a class="skip-link" href="#admin-content">Skip to main content</a>
<div class="admin-shell" data-admin-shell>
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Administration sidebar">
<div class="sidebar-brand-row">
<a class="brand" href="<?= e_attr($basePath) ?>/admin">
<?php if (!empty($brandLogoUrl)): ?>
<span class="brand-logo-frame"><img class="brand-logo" src="<?= e_attr($brandLogoUrl) ?>" alt="" aria-hidden="true"></span>
<?php else: ?>
<span class="brand-mark" aria-hidden="true">W</span>
<?php endif; ?>
<span class="brand-copy"><span class="brand-name"><?= e($siteName) ?></span><span class="brand-meta">Website administration</span></span>
</a>
<button class="sidebar-close" type="button" aria-label="Close administration menu" data-admin-nav-close>×</button>
</div>
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
<div class="system-meta-title">Installed versions</div>
<div class="version-row"><strong>Website Core</strong><span><?= e($coreVersion) ?></span></div>
<?php foreach ($industryVersions as $industry): ?>
<div class="version-row"><strong><?= e($industry['name']) ?></strong><span><?= e($industry['version']) ?></span></div>
<?php endforeach; ?>
</div>
</aside>
<button class="sidebar-overlay" type="button" aria-label="Close administration menu" data-admin-nav-close></button>

<div class="admin-main">
<header class="admin-topbar">
<button class="mobile-menu" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open administration menu" data-admin-nav-open>☰</button>
<div class="page-heading">
<p class="page-context"><?= e($activeGroup) ?></p>
<h1><?= e($pageTitle) ?></h1>
</div>
<div class="header-actions">
<form method="get" action="<?= e_attr($siteBasePath) ?>/index.php" class="header-search">
<input type="hidden" name="p" value="/admin/search">
<input type="search" name="q" placeholder="Search admin…  Ctrl K" aria-label="Search admin">
</form>
<a class="btn secondary small notification-link" href="<?= e_attr($basePath) ?>/admin/notices">
<span class="notification-label">Notifications</span><?php if ($noticeCount): ?><span class="notification-count" aria-label="<?= $noticeCount ?> unread notifications"><?= $noticeCount ?></span><?php endif; ?>
</a>
<a class="btn small" href="<?= e_attr(rtrim($basePath, '/') . '/') ?>" target="_blank" rel="noopener"><span class="open-site-label">Open site</span><span aria-hidden="true">↗</span></a>
<details class="account-menu">
<summary aria-label="Administrator account menu"><span class="account-avatar" aria-hidden="true"><?= e($userInitial) ?></span><span class="account-name"><?= e($username) ?></span></summary>
<div class="account-popover">
<a href="<?= e_attr($basePath) ?>/admin/profile">Profile and security</a>
<form method="post" action="<?= e_attr($basePath) ?>/admin/logout">
<?= $csrf->field() ?><button type="submit">Sign out</button>
</form>
</div>
</details>
</div>
</header>
<main class="admin-content" id="admin-content" tabindex="-1">
<?php if ($flash): ?>
<?php $flashOk = $flash['type'] === 'ok'; ?>
<div class="flash-<?= $flashOk ? 'ok' : 'error' ?>" role="<?= $flashOk ? 'status' : 'alert' ?>">
<?= e($flash['message']) ?><button type="button" class="alert-dismiss" data-dismiss-alert aria-label="Dismiss message">×</button>
</div>
<?php endif; ?>
<?= $content ?>
</main>
</div>
</div>
<script src="<?= e_attr($basePath) ?>/_a/core/js/admin.js&amp;v=<?= e_attr($assetVersion) ?>" defer></script>
</body>
</html>
