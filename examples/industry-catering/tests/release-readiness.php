<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$manifest = json_decode((string) file_get_contents($root . '/addon.json'), true, 512, JSON_THROW_ON_ERROR);
$check($manifest['version'] === '2.1.0', 'Release-readiness suite targets Catering 2.1.0');
$check($manifest['requires']['core'] === '>=2.6.2,<3.0.0' && (int) $manifest['requires']['api'] === 1,
    'Supported Core and Module API ranges are explicit');
foreach (['business.service_area','appearance.collections','commerce.checkout_validation','leads.quotes'] as $service) {
    $check(isset($manifest['requires']['services'][$service]), 'Manifest pins Core service ' . $service);
}
foreach (['README.md', 'CHANGELOG.md', 'docs/OPERATOR_GUIDE.md', 'docs/RELEASE_NOTES_1.0.md', 'docs/RELEASE_NOTES_1.5.md', 'docs/RELEASE_NOTES_2.0.md', 'docs/RELEASE_NOTES_2.0.1.md', 'docs/RELEASE_NOTES_2.0.2.md', 'docs/RELEASE_NOTES_2.1.0.md', 'docs/TESTING.md'] as $file) {
    $check(is_file($root . '/' . $file), $file . ' is packaged');
}

$layout = (string) file_get_contents($root . '/views/layout.php');
$quote = (string) file_get_contents($root . '/views/quote.php');
$contact = (string) file_get_contents($root . '/views/contact.php');
$css = (string) file_get_contents($root . '/assets/css/catering.css');
$themeCss = (string) file_get_contents($root . '/assets/css/themes.css');
$js = (string) file_get_contents($root . '/assets/js/catering.js');
$controller = (string) file_get_contents($root . '/src/CateringController.php');
$workspace = (string) file_get_contents($root . '/src/EventWorkspace.php');
$provider = (string) file_get_contents($root . '/src/Provider.php');
$check(str_contains($layout, '<html lang="en">') && str_contains($layout, 'href="#main-content"') && str_contains($layout, '<main id="main-content">'),
    'Public layout exposes language, skip link, and main landmark');
$check(str_contains($layout, 'aria-controls="primary-navigation"') && str_contains($layout, 'aria-expanded="false"'),
    'Responsive navigation has an explicit disclosure relationship');
$check(str_contains($quote, 'role="alert"') && str_contains($quote, 'aria-live="polite"') && str_contains($quote, '$csrf->field()'),
    'Quote errors, estimate updates, and CSRF controls are accessible');
$check(str_contains($contact, '$csrf->field()') && str_contains($contact, 'autocomplete="email"'),
    'Contact form retains CSRF and autocomplete semantics');
$check(str_contains($css, '@media(max-width:760px)') && str_contains($css, '@media(prefers-reduced-motion:reduce)')
    && str_contains($css, ':focus-visible'), 'Responsive, reduced-motion, and visible-focus rules are present');
$check(str_contains($themeCss, 'position:sticky') && str_contains($themeCss, '75%,transparent')
    && str_contains($themeCss, '[aria-current=page]'), 'Theme navigation is sticky, translucent, and visibly identifies the active page');
$check(str_contains($js, 'is-page-leaving') && str_contains($js, 'window.scrollTo') && str_contains($js, "event.key !== 'Escape'"),
    'Navigation distinguishes page changes, avoids redundant reloads, and supports keyboard dismissal');
$check(!str_contains($js, '.innerHTML') && !str_contains($js, 'document.write') && !preg_match('/\beval\s*\(/', $js),
    'Client rendering avoids HTML injection primitives');
$check(str_contains($controller, 'Csrf::class') && str_contains($controller, 'RateLimiter::class')
    && str_contains($controller, 'JSON_THROW_ON_ERROR'), 'Public writes retain CSRF, abuse controls, and strict JSON snapshots');
$check(str_contains($controller, 'function guard(') && str_contains($controller, "guard('catering.manage')"),
    'Administrator actions enforce server-side capabilities');
$check(!str_contains($controller, 'SELECT * FROM admins') && !str_contains($controller, 'CajunWebPro\\Landscaping'),
    'Catering does not bypass Core identity or another module boundary');
$check(str_contains($workspace, 'synchronizeAcceptedQuotes') && str_contains($workspace, 'productionPlan')
    && str_contains($workspace, 'purchasingPlan'), 'Confirmed-event, production, and purchasing services are packaged');
$check(str_contains($provider, "contentType('catering_venue'") && str_contains($provider, "contentType('catering_case_study'")
    && str_contains($provider, "contentType('catering_review'"), 'Commercial proof content uses Core content types');
$check(str_contains($provider, '->commerce(') && str_contains($provider, '->customerAccounts(')
    && str_contains($provider, '->checkoutValidator('), 'Optional direct ordering uses Core commerce, accounts, and atomic validation');
$themeFiles=glob($root.'/views/themes/*.php')?:[];
$check(count($themeFiles)===10,'Exactly ten distinct Catering layout templates are packaged');
$themeSource=implode("\n",array_map(static fn(string $file):string=>(string)file_get_contents($file),$themeFiles));
$check(count(array_unique(array_map(static fn(string $file):string=>hash_file('sha256',$file),$themeFiles)))===10&&str_contains($themeSource,'theme-heritage-editorial')
    &&str_contains($themeSource,'theme-minimal-luxe'),'All ten theme templates are structurally distinct');
$thumbnailFiles=glob($root.'/assets/images/themes/*.webp')?:[];
$check(count($thumbnailFiles)===10&&count(array_unique(array_map(static fn(string $file):string=>hash_file('sha256',$file),$thumbnailFiles)))===10,
    'Ten distinct theme preview thumbnails are packaged');
foreach($thumbnailFiles as $thumbnail){
    $dimensions=getimagesize($thumbnail);
    $check($dimensions!==false&&$dimensions[0]===600&&$dimensions[1]===400,'Theme thumbnail is a 600x400 image: '.basename($thumbnail));
}
$themePackFiles=glob($root.'/assets/images/theme-packs/*.webp')?:[];
$check(count($themePackFiles)===30&&count(array_unique(array_map(static fn(string $file):string=>hash_file('sha256',$file),$themePackFiles)))===30,
    'Ten themes package thirty distinct hero, feature, and accent photographs');
foreach($themePackFiles as $image){
    $dimensions=getimagesize($image);
    $relative='assets/images/theme-packs/'.basename($image);
    $check($dimensions!==false&&$dimensions[0]>=600&&$dimensions[1]>=400,'Theme photograph has production dimensions: '.basename($image));
    $check(in_array($relative,$manifest['assets']['images'],true),'Theme photograph is declared in addon.json: '.basename($image));
}

$migrationFiles = array_map('basename', glob($root . '/migrations/*.php') ?: []);
$check($migrationFiles === [
    '0001_catering.php', '0002_cajun_menu_images.php', '0003_home_search_metadata.php',
    '0004_core_sales_workspace.php', '0005_menu_planner.php', '0006_catalog_availability.php',
    '0007_event_operations.php', '0008_event_workspace.php',
], 'Migration order is stable and append-only');
echo "Release readiness: {$checks} checks passed.\n";
