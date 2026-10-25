<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FormsWorkspaceTest extends TestCase
{
    public function testBuilderUsesSharedAssetsAndAccessibleOrderingControls(): void
    {
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/forms/index.php');
        $js = (string) file_get_contents(WC_ROOT . '/assets/js/admin.js');
        $css = (string) file_get_contents(WC_ROOT . '/assets/css/admin.css');

        self::assertStringContainsString('data-form-builder', $view);
        self::assertStringContainsString('data-form-preview', $view);
        self::assertStringContainsString('data-form-builder-status', $view);
        self::assertStringNotContainsString('<script', $view);
        self::assertStringContainsString('Drag field', $js);
        self::assertStringContainsString('Move field', $js);
        self::assertStringContainsString('Confirm remove', $js);
        self::assertStringContainsString('.form-builder-layout', $css);
        self::assertStringContainsString('.form-preview-panel', $css);
    }

    public function testInboxExposesBulkConsentMailAndDestructiveSafety(): void
    {
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/forms/submissions.php');
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/FormsController.php');
        $routes = (string) file_get_contents(WC_ROOT . '/core/Application.php');

        self::assertStringContainsString('data-submission-bulk', $view);
        self::assertStringContainsString('Consent evidence', $view);
        self::assertStringContainsString('Notification', $view);
        self::assertStringContainsString('Delete permanently', $view);
        self::assertStringContainsString("['new', 'read', 'archived', 'delete']", $controller);
        self::assertStringContainsString('consent_evidence', $controller);
        self::assertStringContainsString('Response::text("\xEF\xBB\xBF" . $csv)', $controller);
        self::assertStringContainsString("/forms/submissions/{id:\\d+}/delete", $routes);
        self::assertStringContainsString('/forms/submissions/bulk', $routes);
    }

    public function testFailedSavesRetainEnteredFormState(): void
    {
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/FormsController.php');
        self::assertStringContainsString('Your entered settings and fields are still below.', $controller);
        self::assertStringContainsString("'fields_json' => \$fieldsJson", $controller);
        self::assertStringContainsString("'submit_label' => \$r->string", $controller);
    }
}
