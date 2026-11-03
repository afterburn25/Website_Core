<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
session_start();
require __DIR__ . '/bootstrap.php';
if (version_compare(WC_VERSION, '2.6.2', '<')) throw new RuntimeException('Catering 2.1.0 requires Website Core 2.6.2 or newer.');
$geoView = (string) file_get_contents(WC_ROOT . '/admin/views/business/service-area.php');
$coreApplication = (string) file_get_contents(WC_ROOT . '/core/Application.php');
$coreNav = (string) file_get_contents(WC_ROOT . '/core/Admin/Nav.php');
if (!str_contains($geoView, 'name="service_area_center_city"')
    || !str_contains($geoView, 'name="service_area_center_region"')
    || !str_contains($geoView, 'name="service_area_radius_miles"')
    || !str_contains($geoView, 'Range from city (miles)')
    || str_contains($geoView, 'type="date"')) {
    throw new RuntimeException('Core Geo service-area screen does not expose the required city/state/mileage workflow.');
}
if (!str_contains($coreApplication, "'/service-area'")
    || !str_contains($coreNav, "'/admin/service-area', 'Geo service area'")) {
    throw new RuntimeException('Core Geo service-area route or navigation is missing.');
}
$f = cateringFixture();
$service = $f['ctx']->service(Core\Leads\InquiryService::class);
[$id, $error] = $service->submit('cajunwebpro/catering', [
    'name' => 'Core contract check', 'email' => 'contract@example.test', 'message' => 'Detail must open before notes exist.',
]);
if ($error !== null || !$id) throw new RuntimeException('Core inquiry submission failed.');
if (!$service->find($id, 'cajunwebpro/catering')) throw new RuntimeException('Core detail missing.');
$adminId = (int) $f['admins']->findByUsername('owner')['id'];
if ($service->addNote($id, 'cajunwebpro/catering', $adminId, 'Acceptance note') !== null) throw new RuntimeException('Core note save failed.');
$detail = $service->find($id, 'cajunwebpro/catering');
if (($detail['notes'][0]['admin_name'] ?? '') === '') throw new RuntimeException('Core note author is missing.');
if ($service->find($id, 'other/package') !== null) throw new RuntimeException('Core inquiry ownership leaked.');

foreach (['content.presentation','forms.starter','leads.quotes','business.service_area','appearance.collections','commerce.checkout_validation'] as $contract) {
    if ($f['ctx']->coreServiceVersion($contract) === null) throw new RuntimeException('Missing Core service contract: ' . $contract);
}
$themes = $f['ctx']->service(Core\Appearance\ModuleThemeCollections::class)->collections()['cajunwebpro/catering']['variants'] ?? [];
if (count($themes) !== 10) throw new RuntimeException('Catering must register ten Core-owned theme choices.');
$layouts=[]; $heroes=[]; $thumbnails=[];
foreach ($themes as $theme) {
    $layouts[]=$theme['layout'];
    $heroes[]=$theme['media_slots']['hero']['default']??'';
    $thumbnails[]=$theme['thumbnail']??'';
    if (count($theme['palettes']??[]) < 4) throw new RuntimeException('Every Catering theme requires four palettes.');
}
if (count(array_unique($layouts))!==10 || count(array_unique($heroes))!==10 || count(array_unique($thumbnails))!==10) throw new RuntimeException('Theme layouts, default heroes, and thumbnails must be distinct.');

$f['ctx']->geocodingProvider('acceptance-geocoder', static function(string $query): array {
    $far=str_contains($query,'New Orleans');
    return ['status'=>'ok','latitude'=>$far?29.9511:30.4515,'longitude'=>$far?-90.0715:-91.1871,
        'normalized'=>$query,'precision'=>str_contains($query,'Acceptance')?'rooftop':'locality','provider'=>'acceptance-geocoder'];
}, 'Acceptance geocoder');
[$configured,$areaError]=$f['ctx']->configureServiceArea(['city'=>'Baton Rouge','region'=>'LA','country'=>'US','radius_miles'=>'20'],'acceptance-geocoder');
if(!$configured || $areaError!==null || !str_contains($f['ctx']->serviceAreaMap(),'20-mile service radius')) throw new RuntimeException('Shared service area could not be configured.');
$outside=$f['ctx']->checkServiceAddress(['line1'=>'1 Acceptance St','city'=>'New Orleans','region'=>'LA','postal_code'=>'70112'],'acceptance-geocoder');
if(($outside['status']??'')!=='outside' || ($outside['message']??'')!=='This address is currently outside the service area.') throw new RuntimeException('Outside-radius contract failed.');
if(!$f['ctx']->service(Core\Commerce\CheckoutValidation::class)->registered('cajunwebpro/catering')) throw new RuntimeException('Checkout validator was not registered.');

echo "Core 2.6.2 admin-icon, inquiry, dedicated Geo workflow, theme, service-area, quote-workflow, and checkout contracts passed.\n";
