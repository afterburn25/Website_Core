<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Appearance\ModuleThemeCollections;
use Core\Business\ServiceAreaService;
use Core\Commerce\CheckoutValidation;
use Core\Commerce\OrderService;
use Core\Container;
use Core\Content\PageBlocks;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Events\Dispatcher;
use Core\Jobs\JobQueue;
use Core\Jobs\Runner;
use Core\Leads\InquiryService;
use Core\Leads\QuoteService;
use Core\Mail\SystemMail;
use Core\Modules\Manifest;
use Core\Modules\ServiceVersions;
use Core\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class ReusablePlatformTest extends TestCase
{
    private Connection $db;
    private string $tmp;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $this->db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->tmp = sys_get_temp_dir() . '/wc-reuse-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/layouts', 0750, true);
        mkdir($this->tmp . '/assets', 0750, true);
        file_put_contents($this->tmp . '/layouts/editorial.php', '<?php echo "layout";');
        file_put_contents($this->tmp . '/layouts/modern.php', '<?php echo "modern";');
        file_put_contents($this->tmp . '/assets/theme.css', '.theme{}');
        file_put_contents($this->tmp . '/assets/preview.webp', 'preview');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*/*') ?: [] as $file) @unlink($file);
        foreach (glob($this->tmp . '/*', GLOB_ONLYDIR) ?: [] as $dir) @rmdir($dir);
        @rmdir($this->tmp);
    }

    public function testReusablePresentationBlocksRenderSafely(): void
    {
        $blocks = new PageBlocks($this->db);
        $definitions = $blocks->definitions();
        foreach (['faq','gallery','testimonials','services','team','cta'] as $type) self::assertArrayHasKey($type, $definitions);
        [$json, $error] = $blocks->normalize(json_encode([
            ['id'=>'services1','type'=>'services','data'=>['heading'=>'What we do','intro'=>'<p>Shared</p>','items'=>"Catering | Events <script>x</script> | /events"]],
            ['id'=>'team1234','type'=>'team','data'=>['heading'=>'Team','items'=>"Jane | Owner | <b>Leader</b>"]],
        ]));
        self::assertNull($error);
        $html = $blocks->render($json, '/site');
        self::assertStringContainsString('wc-services', $html);
        self::assertStringContainsString('/site/events', $html);
        self::assertStringContainsString('wc-team', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<b>Leader</b>', $html);
    }

    public function testManifestEnforcesNamedServiceVersions(): void
    {
        $manifest = [
            'schema'=>1,'id'=>'acme/demo','type'=>'addon','name'=>'Demo','version'=>'1.0.0','license'=>'MIT',
            'requires'=>['core'=>'>=2.5.0','api'=>1,'services'=>['business.service_area'=>'^1.0.0']],
        ];
        self::assertSame([], Manifest::validate($manifest));
        self::assertSame([], Manifest::compatible($manifest, '2.6.0', 1, '8.4.0', ServiceVersions::all()));
        $manifest['requires']['services']['business.service_area'] = '>=1.0.0 unexpected';
        self::assertNotEmpty(Manifest::validate($manifest));
        $manifest['requires']['services']['business.service_area'] = '^1.0.0';
        $manifest['requires']['services']['missing.service'] = '>=1.0.0';
        self::assertStringContainsString('unavailable Core service', implode(' ', Manifest::compatible($manifest, '2.6.0', 1, '8.4.0')));
    }

    public function testQuoteAttachmentsRemindersAndImmutableDocumentsStayScoped(): void
    {
        $inquiries = new InquiryService($this->db);
        [$inquiryId] = $inquiries->submit('acme/catering', [
            'name'=>'Customer','email'=>'customer@example.test','subject'=>'Event','message'=>'Details',
        ]);
        $quotes = new QuoteService($this->db);
        [$quoteId, $error] = $quotes->save('acme/catering', [
            'inquiry_id'=>$inquiryId,'customer_name'=>'Customer','customer_email'=>'customer@example.test',
            'currency'=>'USD','status'=>'draft',
        ], [['description'=>'Package','quantity'=>1,'unit_price'=>'125.00']]);
        self::assertNull($error);
        $mediaId = (int) $this->db->insert('media', [
            'folder'=>'','filename'=>'proposal.pdf','original_name'=>'proposal.pdf','mime'=>'application/pdf',
            'size_bytes'=>12,'width'=>null,'height'=>null,'alt_text'=>null,'caption'=>null,
            'is_private'=>1,'uploaded_by'=>null,'created_at'=>date('Y-m-d H:i:s'),
        ]);
        [$attachmentId, $attachError] = $quotes->attach((int) $quoteId, 'acme/catering', $mediaId, 'Proposal');
        self::assertNull($attachError); self::assertIsInt($attachmentId);
        self::assertSame($mediaId, (int) $quotes->attachment((int) $attachmentId, 'acme/catering')['media_id']);
        self::assertNull($quotes->attachment((int) $attachmentId, 'acme/landscaping'));
        self::assertSame(1, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('media_usage') . '` WHERE context=? AND context_id=?',
            ['crm.quote.attachment', $attachmentId],
        ));
        $publicMediaId = (int) $this->db->insert('media', [
            'folder'=>'','filename'=>'public.pdf','original_name'=>'public.pdf','mime'=>'application/pdf',
            'size_bytes'=>12,'width'=>null,'height'=>null,'alt_text'=>null,'caption'=>null,
            'is_private'=>0,'uploaded_by'=>null,'created_at'=>date('Y-m-d H:i:s'),
        ]);
        self::assertNotNull($quotes->attach((int) $quoteId, 'acme/catering', $publicMediaId, 'Public')[1]);
        [$reminderId, $reminderError] = $quotes->scheduleReminder(
            (int) $quoteId, 'acme/catering', '-1 minute', 'customer@example.test', 'Review your quote.',
        );
        [$internalReminderId, $internalError] = $quotes->scheduleReminder(
            (int) $quoteId, 'acme/catering', '-1 minute', '', 'Call the customer.', 'internal',
        );
        self::assertNull($internalError); self::assertIsInt($internalReminderId);
        self::assertNull($reminderError); self::assertCount(2, $quotes->dueReminders());
        $container = new Container();
        $queue = new JobQueue($this->db);
        $container->instance(Connection::class, $this->db);
        $container->instance(JobQueue::class, $queue);
        $container->instance(QuoteService::class, $quotes);
        $container->instance(SystemMail::class, new SystemMail($this->db, new SettingsRepository($this->db)));
        $queue->dispatch('crm.quote_reminders');
        self::assertSame(1, (new Runner($container))->run());
        self::assertSame('sent', $this->db->fetchColumn(
            'SELECT status FROM `' . $this->db->table('crm_quote_reminders') . '` WHERE id=?',
            [$reminderId],
        ));
        self::assertSame(1, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('system_messages') . '`',
        ));
        self::assertSame(1, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('admin_notices') . '`',
        ));
        $queue->dispatch('crm.quote_reminders');
        self::assertSame(1, (new Runner($container))->run());
        self::assertSame(1, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('system_messages') . '`',
        ), 'A retry must not queue a duplicate reminder.');
        [$documentId, $documentError] = $quotes->snapshotDocument(
            (int) $quoteId, 'acme/catering', 'proposal', 'Event proposal', '<h1>Proposal</h1><script>bad()</script>', 7,
        );
        self::assertNull($documentError);
        $document = $quotes->document((int) $documentId, 'acme/catering');
        self::assertNotNull($document);
        self::assertStringNotContainsString('<script>', (string) $document['html_snapshot']);
        self::assertNull($quotes->document((int) $documentId, 'acme/landscaping'));
        self::assertCount(1, $quotes->find((int) $quoteId, 'acme/catering')['attachments']);
    }

    public function testServiceAreaCachesGeocodingAndReturnsInsideOutsideUnknownAndOverride(): void
    {
        $settings = new SettingsRepository($this->db);
        $service = new ServiceAreaService($this->db, $settings);
        $calls = 0;
        $geocoder = static function (string $query) use (&$calls): array {
            $calls++;
            if (str_contains($query, 'Ambiguous')) return ['status'=>'ambiguous'];
            if (str_contains($query, 'New Orleans')) return ['status'=>'ok','latitude'=>29.9511,'longitude'=>-90.0715,'normalized'=>$query,'precision'=>'rooftop'];
            if (str_contains($query, '100 Main')) return ['status'=>'ok','latitude'=>30.2300,'longitude'=>-92.0200,'normalized'=>$query,'precision'=>'rooftop'];
            return ['status'=>'ok','latitude'=>30.2241,'longitude'=>-92.0198,'normalized'=>'Lafayette, LA','precision'=>'locality'];
        };
        $service->registerProvider('acme/catering', 'test', $geocoder, 'Test Maps');
        [$ok, $error] = $service->configureRegistered('acme/catering', [
            'city'=>'Lafayette','region'=>'LA','country'=>'US','radius_miles'=>'25',
        ], 'test');
        self::assertTrue($ok, (string) $error);
        $inside = $service->checkRegistered('acme/catering', ['line1'=>'100 Main','city'=>'Lafayette','region'=>'LA','postal_code'=>'70501'], 'test');
        self::assertSame('inside', $inside['status']);
        $callsAfterFirst = $calls;
        self::assertSame('inside', $service->checkRegistered('acme/catering', ['line1'=>'100 Main','city'=>'Lafayette','region'=>'LA','postal_code'=>'70501'], 'test')['status']);
        self::assertSame($callsAfterFirst, $calls, 'Second lookup must use the cache.');
        self::assertSame('outside', $service->checkRegistered('acme/catering', 'New Orleans, LA', 'test')['status']);
        self::assertSame('unknown', $service->checkRegistered('acme/catering', 'Ambiguous address', 'test')['status']);
        self::assertSame('unknown', $service->checkRegistered('acme/catering', '100 Main, Lafayette, LA', 'missing')['status']);
        [$overrideOk] = $service->override('acme/catering', 'New Orleans, LA', true, 'Approved exception', 9);
        self::assertTrue($overrideOk);
        self::assertSame('inside', $service->checkRegistered('acme/catering', 'New Orleans, LA', 'test')['status']);
        self::assertStringContainsString('25-mile service radius', $service->renderMap());
        self::assertStringContainsString('Test Maps', $service->renderMap());
        self::assertSame('test', $service->profile()['provider_key']);
    }

    public function testModuleThemeDraftPublishRollbackAndContrast(): void
    {
        $firstMedia = $this->publicImage('first.jpg');
        $secondMedia = $this->publicImage('second.jpg');
        $service = new ModuleThemeCollections($this->db);
        $service->register('acme/catering', 'Catering layouts', [
            $this->variant('editorial', 'Editorial', 'layouts/editorial.php', '#ffffff', '#111827', true),
            $this->variant('modern', 'Modern', 'layouts/modern.php', '#111827', '#ffffff', true),
        ], $this->tmp, 'industry-catering');
        self::assertSame('assets/preview.webp', $service->collections()['acme/catering']['variants']['editorial']['thumbnail']);
        self::assertGreaterThanOrEqual(4.5, ModuleThemeCollections::contrast('#ffffff', '#111827'));
        [$ok, $error] = $service->saveDraft('acme/catering', 'editorial', 'default', ['hero'=>$firstMedia]);
        self::assertTrue($ok, (string) $error); self::assertTrue($service->publish('acme/catering')[0]);
        self::assertSame('editorial', $service->current('acme/catering')['variant_id']);
        self::assertSame($firstMedia, $service->current('acme/catering')['media']['hero']);
        self::assertSame(1, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('media_usage') . '` WHERE media_id=?', [$firstMedia],
        ));
        self::assertTrue($service->activate('acme/catering', 'modern', 'default', ['hero'=>$secondMedia])[0]);
        self::assertSame('modern', $service->current('acme/catering')['variant_id']);
        self::assertSame($secondMedia, $service->current('acme/catering')['media']['hero']);
        self::assertSame(2, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('media_usage') . '`',
        ));
        self::assertTrue($service->rollback('acme/catering')[0]);
        self::assertSame('editorial', $service->current('acme/catering')['variant_id']);
        self::assertSame($firstMedia, $service->current('acme/catering')['media']['hero']);
    }

    public function testCheckoutRulesRunAgainAtomicallyAndStoreImmutableSnapshot(): void
    {
        $validations = new CheckoutValidation($this->db);
        $calls = 0;
        $validations->register('acme/catering', static function (array $context) use (&$calls): array {
            $calls++;
            if (($context['requested_date'] ?? '') === '') {
                return ['outcome'=>'errors','errors'=>['requested_date'=>'Choose an event date.']];
            }
            return ['outcome'=>'ok','snapshot'=>[
                'capacity_key'=>'2026-11-01','remaining'=>4,'final'=>$context['final'],
                'customer_email'=>'customer@example.test','contact'=>'Customer Name',
            ]];
        });
        $orders = new OrderService($this->db, new Dispatcher(), $validations);
        $product = ['id'=>1,'owner_package'=>'acme/catering','sku'=>'DINNER','name'=>'Dinner','slug'=>'dinner','price_minor'=>2500,'track_inventory'=>0,'stock_quantity'=>null];
        $cart = ['items'=>[['product'=>$product,'quantity'=>2,'line_total_minor'=>5000]],'subtotal'=>5000,'currency'=>'USD'];
        $base = ['name'=>'Customer','email'=>'customer@example.test','fulfillment'=>'pickup','payment_method'=>'venmo'];
        [$invalidOrder, $invalidError, $invalidValidation] = $orders->place(
            array_replace($base, ['email'=>'invalid']), $cart, 'acme/catering', ['pickup'], ['venmo'=>['label'=>'Venmo']],
        );
        self::assertNull($invalidOrder); self::assertNotNull($invalidError); self::assertSame('ok', $invalidValidation['outcome']);
        [$order, $error, $failed] = $orders->place($base, $cart, 'acme/catering', ['pickup'], ['venmo'=>['label'=>'Venmo']]);
        self::assertNull($order); self::assertSame('errors', $failed['outcome']);
        self::assertSame('Choose an event date.', $failed['errors']['requested_date']);
        [$order, $error, $result] = $orders->place($base + ['requested_date'=>'2026-11-01'], $cart, 'acme/catering', ['pickup'], ['venmo'=>['label'=>'Venmo']]);
        self::assertNull($error); self::assertNotNull($order); self::assertSame('ok', $result['outcome']);
        self::assertSame(3, $calls, 'One failed preflight plus successful preflight and final validation.');
        $snapshot = $this->db->fetch('SELECT * FROM `' . $this->db->table('commerce_validation_snapshots') . '` WHERE order_id=?', [(int) $order['id']]);
        self::assertNotFalse($snapshot);
        self::assertSame('ok', $snapshot['outcome']);
        self::assertStringNotContainsString('customer@example.test', (string) $snapshot['snapshot_json']);
        self::assertStringNotContainsString('Customer Name', (string) $snapshot['snapshot_json']);
    }

    public function testCheckoutFinalFailureRollsBackOrderAndSupportsSafeOutcomes(): void
    {
        $validations = new CheckoutValidation($this->db);
        $validations->register('acme/capacity', static fn (array $context): array => $context['final']
            ? ['outcome'=>'quote_only','message'=>'Capacity changed; request a quote.','snapshot'=>['capacity'=>'full']]
            : ['outcome'=>'ok']);
        $orders = new OrderService($this->db, new Dispatcher(), $validations);
        $product = ['id'=>2,'owner_package'=>'acme/capacity','sku'=>'EVENT','name'=>'Event','slug'=>'event','price_minor'=>5000,'track_inventory'=>0,'stock_quantity'=>null];
        $cart = ['items'=>[['product'=>$product,'quantity'=>1,'line_total_minor'=>5000]],'subtotal'=>5000,'currency'=>'USD'];
        [$order, $error, $result] = $orders->place(
            ['name'=>'Customer','email'=>'customer@example.test','fulfillment'=>'pickup','payment_method'=>'venmo'],
            $cart, 'acme/capacity', ['pickup'], ['venmo'=>['label'=>'Venmo']],
        );
        self::assertNull($order);
        self::assertSame('Capacity changed; request a quote.', $error);
        self::assertSame('quote_only', $result['outcome']);
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('commerce_orders') . '`'));
        self::assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('commerce_validation_snapshots') . '`'));

        $validations->register('acme/manual', static fn (): array => ['outcome'=>'manual_review']);
        self::assertSame('manual_review', $validations->validate('acme/manual', [], false)['outcome']);
        $validations->register('acme/failure', static function (): never { throw new \RuntimeException('private detail'); });
        $failure = $validations->validate('acme/failure', [], false);
        self::assertSame('errors', $failure['outcome']);
        self::assertStringNotContainsString('private detail', $failure['errors']['_form']);
        $validations->register('acme/slow', static function (): array { usleep(120000); return []; }, ['timeout_seconds'=>0.1]);
        self::assertSame('timeout', $validations->validate('acme/slow', [], false)['snapshot']['failure']);
    }

    private function publicImage(string $filename): int
    {
        return (int) $this->db->insert('media', [
            'folder'=>'','filename'=>$filename,'original_name'=>$filename,'mime'=>'image/jpeg',
            'size_bytes'=>12,'width'=>100,'height'=>100,'alt_text'=>null,'caption'=>null,
            'is_private'=>0,'uploaded_by'=>null,'created_at'=>date('Y-m-d H:i:s'),
        ]);
    }

    private function variant(string $id, string $name, string $layout, string $background, string $text, bool $media = false): array
    {
        return [
            'id'=>$id,'name'=>$name,'description'=>$name . ' description','layout'=>$layout,
            'thumbnail'=>'assets/preview.webp',
            'assets'=>['css'=>['assets/theme.css']],
            'palettes'=>['default'=>['name'=>'Default','tokens'=>[
                'background'=>$background,'text'=>$text,'accent'=>'#0369a1',
            ]]],
            'media_slots'=>$media ? ['hero'=>['label'=>'Hero image']] : [],
        ];
    }
}
