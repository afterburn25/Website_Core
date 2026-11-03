<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
session_start();
require __DIR__ . '/bootstrap.php';

$checks=0;
$verify=static function(bool $condition,string $message)use(&$checks):void{if(!$condition)throw new RuntimeException($message);$checks++;};
$f=cateringFixture();
$db=$f['db']; $settings=$f['settings']; $ctx=$f['ctx'];

$ctx->geocodingProvider('platform-test',static function(string $query):array{
    $far=str_contains($query,'New Orleans');
    return ['status'=>'ok','latitude'=>$far?29.9511:30.4515,'longitude'=>$far?-90.0715:-91.1871,
        'normalized'=>$query,'precision'=>$far?'rooftop':'locality','provider'=>'platform-test'];
},'Platform acceptance');
[$configured,$error]=$ctx->configureServiceArea(['city'=>'Baton Rouge','region'=>'LA','country'=>'US','radius_miles'=>'20'],'platform-test');
$verify($configured&&$error===null,'Core service area configures through the module provider');
$verify(str_contains($ctx->serviceAreaMap(),'20-mile service radius'),'Core map renders the configured radius');

$package=$db->fetch('SELECT * FROM `'.$db->table('catering_packages').'` WHERE visible=1 ORDER BY id LIMIT 1');
$verify($package!==null,'Starter direct-order package exists');
$verify($ctx->service(Core\Commerce\ProductRepository::class)->all('cajunwebpro/catering',true)===[],
    'Quote-only default leaves Core Commerce dormant without creating product rows');
$settings->set('catering.ordering_mode','mixed');
$settings->set('catering.direct_order_package_ids',[(int)$package['id']]);
$settings->set('catering.delivery_zones',[['name'=>'Acceptance zone','postal_codes'=>['*'],'fee_cents'=>0,'minimum_order_cents'=>0]]);
$commerce=new CajunWebPro\Catering\CommerceIntegration($ctx,$db,$settings);
$commerce->syncProducts();
$product=$ctx->service(Core\Commerce\ProductRepository::class)->all('cajunwebpro/catering',true)[0]??null;
$verify($product!==null&&$product['sku']==='CATERING-PKG-'.(int)$package['id'],'Eligible package synchronizes to one Core product');
$date=(new DateTimeImmutable('today'))->modify('+45 days')->format('Y-m-d');
$cart=['items'=>[['product'=>$product,'quantity'=>(int)$package['guest_min'],'line_total_minor'=>(int)$product['price_minor']*(int)$package['guest_min']]],'subtotal'=>(int)$product['price_minor']*(int)$package['guest_min'],'currency'=>'USD'];
$pickup=$ctx->service(Core\Commerce\CheckoutValidation::class)->validate('cajunwebpro/catering',['checkout'=>[
    'requested_date'=>$date,'requested_time'=>'12:00','fulfillment'=>'pickup','payment_method'=>'manual',
],'cart'=>$cart],false);
$verify($pickup['outcome']==='ok','Eligible pickup order passes atomic checkout validation');
$outside=$ctx->service(Core\Commerce\CheckoutValidation::class)->validate('cajunwebpro/catering',['checkout'=>[
    'requested_date'=>$date,'requested_time'=>'12:00','fulfillment'=>'delivery','payment_method'=>'manual',
    'address_line1'=>'1 Acceptance St','city'=>'New Orleans','region'=>'LA','postal_code'=>'70112','country'=>'US',
],'cart'=>$cart],true);
$verify($outside['outcome']==='errors'&&($outside['errors']['address_line1']??'')==='This address is currently outside our service area.',
    'Outside delivery receives the required accessible field error');

$themes=$ctx->service(Core\Appearance\ModuleThemeCollections::class)->collections()['cajunwebpro/catering']['variants']??[];
$verify(count($themes)===10,'Ten Catering layouts register with Core');
foreach($themes as $theme)$verify(count($theme['palettes'])>=4,'Every layout provides four accessible palettes');
$home=$f['controller']->home(cateringRequest('GET','/'));
$verify(str_contains($home->body(),'theme-heritage-editorial'),'Deterministic published fallback renders a real Catering theme');
$verify(str_contains($home->body(),'Order online')&&str_contains($home->body(),'request-a-quote'),
    'Mixed mode exposes both Core ordering and quotation paths');
$themeService=$ctx->service(Core\Appearance\ModuleThemeCollections::class);
foreach($themes as $themeId=>$themeDefinition){
    $paletteId=(string)array_key_first($themeDefinition['palettes']);
    [$activated,$activateError]=$themeService->activate('cajunwebpro/catering',(string)$themeId,$paletteId);
    $rendered=$f['controller']->home(cateringRequest('GET','/'))->body();
    $verify($activated&&$activateError===null
        &&str_contains($rendered,'theme-'.$themeId)&&str_contains($rendered,'themes.css')
        &&str_contains($rendered,'id="main-content"')
        &&str_contains($rendered,'theme-packs/'.$themeId.'-hero.webp')
        &&str_contains($rendered,'theme-packs/'.$themeId.'-feature.webp')
        &&str_contains($rendered,'theme-packs/'.$themeId.'-accent.webp'),
        $themeDefinition['name'].' activates with its own complete photography pack');
}
$area=$f['controller']->serviceArea(cateringRequest('GET','/service-area'));
$verify(str_contains($area->body(),'wc-service-area-map'),'Public service-area page renders the Core map');

$quoteService=$ctx->service(Core\Leads\QuoteService::class);
[$quoteId,$quoteError]=$quoteService->save('cajunwebpro/catering',[
    'customer_name'=>'Document Customer','customer_email'=>'docs@example.test','currency'=>'USD','status'=>'draft',
], [['description'=>'Catering package','quantity'=>1,'unit_price'=>'100.00']]);
$verify($quoteError===null&&$quoteId!==null,'Core quote fixture created');
[$documentId,$documentError]=$ctx->quoteDocument((int)$quoteId,'proposal','Catering proposal','<h1>Proposal</h1><p>Versioned terms.</p>');
$verify($documentId!==null&&$documentError===null,'Catering stores immutable proposal HTML through Core');
[$reminderId,$reminderError]=$ctx->quoteReminder((int)$quoteId,'+2 days','docs@example.test','Please review your catering proposal.');
$verify($reminderId!==null&&$reminderError===null,'Catering schedules an idempotent Core reminder');

$schema=(new CajunWebPro\Catering\StructuredData($ctx))->forRequest(cateringRequest('GET','/faq'));
$verify(($schema[0]['@type']??'')==='Caterer'&&($schema[1]['@type']??'')==='FAQPage','Catering supplies validated public schema data');

echo "Core 2.6 platform integrations: {$checks} checks passed.\n";
