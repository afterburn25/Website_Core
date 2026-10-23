<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\I18n\Translator;
use Core\View\View;
use PHPUnit\Framework\TestCase;

/**
 * Structural accessibility invariants every shipped theme must satisfy:
 * language attribute, main landmark, labelled navigation, non-empty
 * title, viewport meta. Plus: every visible form control in the reference
 * inquiry form is labelled (the honeypot is aria-hidden and out of the
 * tab order). Not a substitute for an audited WCAG review — these are
 * the machine-checkable floor the spec requires as release evidence.
 */
final class AccessibilityTest extends TestCase
{
    protected function setUp(): void
    {
        Translator::setInstance(new Translator('en'));
    }

    private function renderLayout(string $templatesDir): string
    {
        $view = new View();
        $view->addResolver('t', fn (string $v): ?string =>
            is_file($templatesDir . '/' . $v . '.php')
                ? $templatesDir . '/' . $v . '.php' : null);
        return $view->render('t:layout', [
            'content' => '<h1>Heading</h1><p>Body.</p>',
            'siteName' => 'Test Site',
            'tagline' => 'Tagline',
            'basePath' => '',
            'pages' => [['title' => 'Home', 'slug' => 'home', 'url' => '']],
            'page' => ['title' => 'Home', 'meta_title' => 'Home'],
        ]);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function themeProvider(): array
    {
        return [
            'base'  => [WC_THEMES . '/base/templates'],
            'terra' => [dirname(WC_ROOT) . '/examples/theme-terra/templates'],
            'noir'  => [dirname(WC_ROOT) . '/examples/theme-noir/templates'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('themeProvider')]
    public function testThemeLayoutLandmarks(string $templatesDir): void
    {
        $html = $this->renderLayout($templatesDir);

        $this->assertMatchesRegularExpression('/<html lang="[a-zA-Z-]+"/', $html, 'lang attribute');
        $this->assertMatchesRegularExpression('/<title>[^<]*\S[^<]*<\/title>/', $html, 'non-empty title');
        $this->assertStringContainsString('name="viewport"', $html);
        $this->assertMatchesRegularExpression('/<nav[^>]*aria-label="[^"]+"/', $html, 'labelled nav landmark');
        $this->assertMatchesRegularExpression('/<main[\s>]/', $html, 'main landmark');
        // Heading hierarchy present in rendered output.
        $this->assertStringContainsString('<h1>', $html);
    }

    public function testAdminLayoutAccessibilityControls(): void
    {
        $adminLayout = (string) file_get_contents(WC_ROOT . '/admin/views/layouts/admin.php');
        $this->assertStringContainsString('aria-label="Admin"', $adminLayout, 'admin nav labelled');
        $this->assertStringContainsString('aria-current="page"', $adminLayout, 'active page is exposed to assistive technology');
        $this->assertStringContainsString('<details class="nav-group"', $adminLayout, 'admin categories are collapsible');
        $this->assertStringContainsString(' data-nav-group=', $adminLayout, 'admin categories have persistent identities');
        $this->assertStringContainsString(' open>', $adminLayout, 'admin categories default expanded');
        $this->assertStringContainsString('wc-admin-nav-state-v1', $adminLayout, 'admin category state persists');
        $this->assertStringContainsString('focus-visible', $adminLayout, 'focus-visible outlines present');
        $this->assertStringContainsString('prefers-reduced-motion', $adminLayout, 'reduced-motion support present');
    }

    public function testThemeReducedMotionSupport(): void
    {
        $themes = [
            WC_THEMES . '/base/assets/css/style.css',
            dirname(WC_ROOT) . '/examples/theme-noir/assets/css/style.css',
            dirname(WC_ROOT) . '/examples/theme-terra/assets/css/style.css',
        ];
        foreach ($themes as $cssPath) {
            $this->assertFileExists($cssPath, "$cssPath exists");
            $css = (string) file_get_contents($cssPath);
            $this->assertStringContainsString('prefers-reduced-motion', $css, "$cssPath supports reduced motion");
        }
    }

    public function testMediaThumbnailAltTextNotAlwaysEmpty(): void
    {
        $mediaIndex = (string) file_get_contents(WC_ROOT . '/admin/views/media/index.php');
        $this->assertMatchesRegularExpression(
            '/alt="[^"]+"/',
            $mediaIndex,
            'Media thumbnail has descriptive alt text (not empty)'
        );
    }

    public function testInquiryFormLabelsAllVisibleControls(): void
    {
        $form = (string) file_get_contents(
            dirname(WC_ROOT) . '/examples/addon-inquiry/views/form.php'
        );
        // Every id="…" on an input must have a matching for="…" label,
        // unless the control sits inside the aria-hidden honeypot block.
        preg_match_all('/<input id="([^"]+)"/', $form, $ids);
        foreach ($ids[1] as $id) {
            $this->assertMatchesRegularExpression(
                '/for="' . preg_quote($id, '/') . '"/',
                $form,
                "input #{$id} has no associated label"
            );
        }
        $this->assertStringContainsString('aria-hidden="true"', $form, 'honeypot hidden from AT');
    }
}
