<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CommercialPolishTest extends TestCase
{
    public function testSharedShellProvidesPersistentProfessionalWorkspaceControls(): void
    {
        $layout = (string) file_get_contents(WC_ROOT . '/admin/views/layouts/admin.php');
        $css = (string) file_get_contents(WC_ROOT . '/assets/css/admin.css');
        $js = (string) file_get_contents(WC_ROOT . '/assets/js/admin.js');

        self::assertStringContainsString('data-sidebar-collapse', $layout);
        self::assertStringContainsString('data-ui-compact', $layout);
        self::assertStringContainsString('data-confirm-dialog', $layout);
        self::assertStringContainsString('wc-admin-ui-v2', $js);
        self::assertStringContainsString('admin-sidebar-collapsed', $css);
        self::assertStringContainsString('admin-compact', $css);
        self::assertStringContainsString("'beforeunload'", $js);
        self::assertStringContainsString('[data-auto-submit]', $js);
        self::assertStringContainsString('[data-image-preview]', $js);
        self::assertStringContainsString('password-visibility', $js);
        self::assertStringContainsString('Filter visible rows', $js);
        self::assertStringContainsString('form.dataset.busyLabel', $js);
        self::assertStringContainsString('pendingConfirmation.autoControl.checked', $js);
    }

    public function testExistingBinaryFunctionsUseOneSwitchContract(): void
    {
        foreach ([
            'operations/index.php' => 'Maintenance mode',
            'security/index.php' => 'Require multi-factor authentication',
            'forms/index.php' => 'Accept submissions',
            'privacy/index.php' => 'Show privacy choices',
            'integrations/index.php' => 'inline-toggle-form',
            'users/index.php' => 'inline-toggle-form',
            'modules/index.php' => 'stateToggle',
        ] as $view => $evidence) {
            $body = (string) file_get_contents(WC_ROOT . '/admin/views/' . $view);
            self::assertStringContainsString('toggle-control', $body, $view);
            self::assertStringContainsString($evidence, $body, $view);
        }
    }

    public function testInactiveSmtpFieldsCannotEraseStoredConfiguration(): void
    {
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/settings/index.php');
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/SettingsController.php');
        self::assertStringContainsString('data-toggle-target="[data-smtp-field]"', $view);
        self::assertStringContainsString("if (\$transport === 'smtp')", $controller);
        self::assertStringContainsString('must never erase a working SMTP configuration', $controller);
    }
}
