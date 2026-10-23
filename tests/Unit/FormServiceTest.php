<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Forms\FormService;
use Core\Security\Csrf;
use PHPUnit\Framework\TestCase;

final class FormServiceTest extends TestCase
{
    private Connection $db;
    private FormService $forms;

    protected function setUp(): void
    {
        $this->db = Connection::sqlite();
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->forms = new FormService($this->db);
        $_SESSION = [];
    }

    public function testBuildsValidatesStoresAndExportsSubmissionData(): void
    {
        [$id, $error] = $this->forms->save(null, 'Contact', json_encode([
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
            ['name' => 'consent', 'label' => 'I agree', 'type' => 'checkbox', 'required' => true],
        ], JSON_THROW_ON_ERROR), 'owner@example.com', 'New lead', 'Thanks', true, 1);
        self::assertNull($error);
        self::assertGreaterThan(0, $id);

        [$badId, $errors] = $this->forms->submit($id, ['email' => 'bad', 'message' => ''], 'hash');
        self::assertNull($badId);
        self::assertNotEmpty($errors);

        [$submission, $errors, $data] = $this->forms->submit($id, [
            'email' => 'person@example.com', 'message' => '<b>Hello</b>', 'consent' => '1',
        ], 'hash');
        self::assertNotNull($submission);
        self::assertSame([], $errors);
        self::assertSame('Hello', $data['message']);
        self::assertCount(1, $this->forms->submissions($id));
    }

    public function testRendersAccessibleCsrfProtectedForm(): void
    {
        [$id] = $this->forms->save(null, 'Newsletter', json_encode([
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true],
        ], JSON_THROW_ON_ERROR), '', '', 'Done', true, 1);
        $html = $this->forms->render($id, '/demo', new Csrf());
        self::assertStringContainsString('class="wc-public-form"', $html);
        self::assertStringContainsString('name="_csrf"', $html);
        self::assertStringContainsString('for="wc-form-', $html);
        self::assertStringContainsString('/index.php?p=/forms/' . $id . '/submit', $html);
    }

    public function testArchivePreservesExistingSubmissions(): void
    {
        [$id] = $this->forms->save(null, 'One field', json_encode([
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
        ], JSON_THROW_ON_ERROR), '', '', '', true, 1);
        $this->forms->submit($id, ['name' => 'Jane'], 'hash');
        $this->forms->archive($id);
        self::assertCount(1, $this->forms->submissions($id));
        [$submission, $errors] = $this->forms->submit($id, ['name' => 'Later'], 'hash');
        self::assertNull($submission);
        self::assertNotEmpty($errors);
    }
}
