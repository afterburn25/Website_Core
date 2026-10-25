<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SeoTranslationPatternsWorkspaceTest extends TestCase
{
    public function testRedirectManagerOwnsCompleteWorkflow(): void
    {
        $seo = (string) file_get_contents(WC_ROOT . '/admin/views/seo/index.php');
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/SeoController.php');
        self::assertStringContainsString('Redirect manager', $seo);
        self::assertStringContainsString('redirect_save', $seo);
        self::assertStringContainsString('redirect_delete', $seo);
        self::assertStringContainsString('redirects.csv', $seo);
        self::assertStringContainsString('first_error', $controller);
        self::assertStringContainsString('2,000 data rows', $controller);
    }

    public function testTranslationWorkspaceIncludesProgressLifecycleAndSideBySideEditing(): void
    {
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/translations/index.php');
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/TranslationsController.php');
        self::assertStringContainsString('language_toggle', $view);
        self::assertStringContainsString('progress-track', $view);
        self::assertStringContainsString('translation-pair-grid', $view);
        self::assertStringContainsString('data-translation-preview-title', $view);
        self::assertStringContainsString('$submittedTranslation = $r->post', $controller);
        self::assertStringContainsString('translations.page_deleted', $controller);
    }

    public function testPatternLibraryIncludesPreviewApplyDuplicateRenameAndDelete(): void
    {
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/patterns/index.php');
        foreach (['pattern-preview', 'value="apply"', 'value="duplicate"', 'value="rename"', 'value="delete"', 'data-pattern-filter'] as $evidence) {
            self::assertStringContainsString($evidence, $view);
        }
        self::assertStringContainsString('current page as a revision', $view);
    }
}
