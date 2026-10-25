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
        self::assertSame(1, $this->forms->submissionCount($id, 'new'));
        self::assertSame(1, $this->forms->submissionCount(null, '', 'person@example.com'));
        self::assertTrue($this->forms->setSubmissionStatus((int) $submission, 'archived'));
        self::assertSame(1, $this->forms->submissionCount($id, 'archived'));
        self::assertFalse($this->forms->setSubmissionStatus((int) $submission, 'invalid'));
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

    public function testFieldPresentationValidationAndActivationLifecycle(): void
    {
        [$id, $error] = $this->forms->save(null, 'Detailed contact', json_encode([
            [
                'name' => 'full_name', 'label' => 'Full name', 'type' => 'text',
                'required' => true, 'placeholder' => 'Jane Smith',
                'help' => 'Enter the name we should use when replying.', 'autocomplete' => 'name',
            ],
            [
                'name' => 'service', 'label' => 'Service', 'type' => 'select',
                'required' => true, 'options' => ['Catering', 'Delivery'],
            ],
            ['name' => 'event_date', 'label' => 'Event date', 'type' => 'date', 'required' => true],
        ], JSON_THROW_ON_ERROR), '', '', '', true, 1, 'Send enquiry');

        self::assertNull($error);
        $html = $this->forms->render($id, '/demo', new Csrf());
        self::assertStringContainsString('placeholder="Jane Smith"', $html);
        self::assertStringContainsString('autocomplete="name"', $html);
        self::assertStringContainsString('aria-describedby="wc-form-' . $id . '-full_name-help"', $html);
        self::assertStringContainsString('Send enquiry', $html);

        [$submission, $errors] = $this->forms->submit($id, [
            'full_name' => 'Jane',
            'service' => 'Not offered',
            'event_date' => '2026-02-31',
        ], 'hash');
        self::assertNull($submission);
        self::assertCount(2, $errors);

        self::assertTrue($this->forms->setActive($id, false));
        self::assertStringContainsString('Form unavailable', $this->forms->render($id, '', new Csrf()));
        self::assertTrue($this->forms->setActive($id, true));
        self::assertStringContainsString('Send enquiry', $this->forms->render($id, '', new Csrf()));
        self::assertFalse($this->forms->setActive(999999, true));
    }

    public function testRejectsAmbiguousFieldDefinitionsInsteadOfSilentlyDroppingThem(): void
    {
        [$id, $error] = $this->forms->save(null, 'Bad form', json_encode([
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'email', 'label' => 'Email again', 'type' => 'email'],
        ], JSON_THROW_ON_ERROR), '', '', '', true, 1);
        self::assertSame(0, $id);
        self::assertStringContainsString('unique', (string) $error);

        [$id, $error] = $this->forms->save(null, 'Bad select', json_encode([
            ['name' => 'choice', 'label' => 'Choice', 'type' => 'select', 'options' => []],
        ], JSON_THROW_ON_ERROR), '', '', '', true, 1);
        self::assertSame(0, $id);
        self::assertStringContainsString('dropdown option', (string) $error);
    }

    public function testBulkSubmissionWorkflowAndNotificationEvidence(): void
    {
        $formColumns = array_column($this->db->fetchAll('PRAGMA table_info(forms)'), 'name');
        $submissionColumns = array_column($this->db->fetchAll('PRAGMA table_info(form_submissions)'), 'name');
        self::assertContains('submit_label', $formColumns);
        self::assertContains('notification_message_id', $submissionColumns);
        self::assertContains('updated_at', $submissionColumns);

        [$id] = $this->forms->save(null, 'Requests', json_encode([
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'consent', 'label' => 'I agree', 'type' => 'checkbox', 'required' => true],
        ], JSON_THROW_ON_ERROR), '', '', '', true, 1);
        [$first] = $this->forms->submit($id, ['name' => 'One', 'consent' => '1'], 'hash-one');
        [$second] = $this->forms->submit($id, ['name' => 'Two', 'consent' => '1'], 'hash-two');
        $messageId = (int) $this->db->insert('system_messages', [
            'to' => 'owner@example.com', 'subject' => 'New request', 'body' => 'Body',
            'status' => 'queued', 'dedupe_key' => null, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->forms->attachNotification((int) $first, $messageId);

        $rows = $this->forms->submissions($id);
        $firstRow = current(array_filter($rows, static fn (array $row): bool => (int) $row['id'] === (int) $first));
        self::assertIsArray($firstRow);
        self::assertSame('queued', $firstRow['notification_status']);
        $consent = json_decode((string) $firstRow['consent_json'], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($consent['consent']['accepted']);
        self::assertNotEmpty($consent['consent']['recorded_at']);

        $result = $this->forms->bulkSubmissionAction([(int) $first, (int) $second], 'archived');
        self::assertSame(['updated' => 2, 'missing' => 0], $result);
        self::assertSame(2, $this->forms->submissionCount($id, 'archived'));

        $result = $this->forms->bulkSubmissionAction([(int) $first, 999999], 'delete');
        self::assertSame(['updated' => 1, 'missing' => 1], $result);
        self::assertSame(1, $this->forms->submissionCount($id));
    }
}
