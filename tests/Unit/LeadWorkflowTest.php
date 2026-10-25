<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Business\BusinessProfile;
use Core\Auth\AdminRepository;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Leads\InquiryService;
use Core\Leads\QuoteService;
use Core\Settings\SettingsRepository;
use Core\Security\Csrf;
use Core\View\View;
use PHPUnit\Framework\TestCase;

final class LeadWorkflowTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $this->db=Connection::sqlite(':memory:','wc_');
        (new Migrator($this->db,WC_ROOT.'/database/migrations'))->migrate();
    }

    public function testBusinessProfileSeedsOnceAndPreservesEdits(): void
    {
        $profile=new BusinessProfile(new SettingsRepository($this->db));
        $profile->seed('acme/catering',['name'=>'Catering Co.','email'=>'hello@example.test']);
        self::assertSame('Catering Co.',$profile->get()['name']);
        self::assertSame([], $profile->save(['name'=>'Edited Company','email'=>'owner@example.test','website'=>'https://example.test']));
        $profile->seed('acme/catering',['name'=>'Overwritten']);
        self::assertSame('Edited Company',$profile->get()['name']);
        self::assertSame(['Business email must be a valid email address.'],$profile->save(['email'=>'invalid']));
    }

    public function testInquiryAndSecureQuoteAcceptanceFlow(): void
    {
        $inquiries=new InquiryService($this->db);
        [$inquiryId,$error]=$inquiries->submit('acme/catering',[
            'name'=>'Jane Customer','email'=>'jane@example.test','subject'=>'Wedding','message'=>'Need service for 100 guests.',
        ],42);
        self::assertNull($error);self::assertNotNull($inquiryId);
        [$duplicate,$duplicateError]=$inquiries->submit('acme/catering',[
            'name'=>'Jane Customer','email'=>'jane@example.test','subject'=>'Wedding','message'=>'Duplicate delivery.',
        ],42);
        self::assertNull($duplicateError);self::assertSame($inquiryId,$duplicate);
        self::assertSame(1,$inquiries->count('acme/catering'));

        $quotes=new QuoteService($this->db);
        [$quoteId,$quoteError,$token]=$quotes->save('acme/catering',[
            'inquiry_id'=>$inquiryId,'customer_name'=>'Jane Customer','customer_email'=>'jane@example.test',
            'currency'=>'USD','status'=>'sent','discount'=>'10.00','tax'=>'5.00','valid_until'=>date('Y-m-d',strtotime('+7 days')),
        ],[['description'=>'Catering package','quantity'=>2,'unit_price'=>'125.00']]);
        self::assertNull($quoteError);self::assertNotNull($quoteId);self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/',(string)$token);
        self::assertSame(24500,(int)$quotes->find((int)$quoteId,'acme/catering')['total_minor']);
        self::assertNotNull($quotes->byToken((string)$token));
        self::assertTrue($quotes->respond((string)$token,true));
        self::assertSame('accepted',$quotes->find((int)$quoteId,'acme/catering')['status']);
        self::assertNull($quotes->byToken((string)$token));
    }

    public function testInquiryDetailNotesUseCanonicalAdminSchemaAndStayPackageScoped(): void
    {
        $admins = new AdminRepository($this->db);
        $adminId = $admins->create(
            'case-owner',
            'owner@example.test',
            password_hash('correct-horse-12', PASSWORD_DEFAULT),
            'owner',
        );
        $service = new InquiryService($this->db);
        [$inquiryId, $error] = $service->submit('acme/catering', [
            'name' => 'Morgan Client',
            'email' => 'morgan@example.test',
            'subject' => 'Company lunch',
            'message' => 'Lunch for 40 people.',
        ]);
        self::assertNull($error);

        $detail = $service->find((int) $inquiryId, 'acme/catering');
        self::assertIsArray($detail);
        self::assertSame([], $detail['notes']);
        self::assertNull($service->find((int) $inquiryId, 'acme/landscaping'));
        self::assertNull($service->addNote((int) $inquiryId, 'acme/catering', $adminId, 'Confirm delivery window.'));

        $detail = $service->find((int) $inquiryId, 'acme/catering');
        self::assertSame('case-owner', $detail['notes'][0]['admin_name']);
        self::assertSame('Confirm delivery window.', $detail['notes'][0]['body']);

        // Exercise the same administrator selector query and detail template
        // used by LeadsController against the canonical username-only schema.
        $availableAdmins = $this->db->fetchAll(
            'SELECT id,username AS name FROM `' . $this->db->table('admins')
            . '` WHERE disabled = 0 ORDER BY username'
        );
        self::assertSame('case-owner', $availableAdmins[0]['name']);
        $view = new View();
        $view->addResolver('admin', static function (string $name): ?string {
            $path = WC_ROOT . '/admin/views/' . $name . '.php';
            return is_file($path) ? $path : null;
        });
        $html = $view->render('leads/inquiry', [
            'inquiry' => $detail,
            'admins' => $availableAdmins,
            'statuses' => InquiryService::STATUSES,
            'priorities' => InquiryService::PRIORITIES,
            'quotesEnabled' => true,
            'error' => null,
            'csrf' => new Csrf(),
            'basePath' => '/catering/index.php?p=',
        ]);
        self::assertStringContainsString('Morgan Client', $html);
        self::assertStringContainsString('case-owner', $html);
        self::assertStringContainsString('Confirm delivery window.', $html);
    }
}
