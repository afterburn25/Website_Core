<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Content\Sanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\HTMLPurifier::class)) {
            $this->markTestSkipped('htmlpurifier not installed');
        }
    }

    public function testScriptStripped(): void
    {
        $out = (new Sanitizer())->html('<p>hi</p><script>alert(1)</script>');
        $this->assertStringContainsString('hi', $out);
        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('alert', $out);
    }

    public function testEventHandlersAndJsUrlsStripped(): void
    {
        $out = (new Sanitizer())->html(
            '<p onclick="x()">a</p><a href="javascript:alert(1)">b</a><img src="x" onerror="x()">'
        );
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('onerror', $out);
    }

    public function testIframeAndFormStripped(): void
    {
        $out = (new Sanitizer())->html('<iframe src="https://evil"></iframe><form><input></form>');
        $this->assertStringNotContainsString('iframe', $out);
        $this->assertStringNotContainsString('<form', $out);
        $this->assertStringNotContainsString('<input', $out);
    }

    public function testLegitimateMarkupSurvives(): void
    {
        $in = '<h2>Menu</h2><p>Our <strong>specials</strong> <a href="https://example.com" title="x">link</a></p><ul><li>one</li></ul>';
        $out = (new Sanitizer())->html($in);
        $this->assertStringContainsString('<h2>Menu</h2>', $out);
        $this->assertStringContainsString('<strong>specials</strong>', $out);
        $this->assertStringContainsString('href="https://example.com"', $out);
        $this->assertStringContainsString('<li>one</li>', $out);
    }

    public function testRelativeLinksSurvive(): void
    {
        $out = (new Sanitizer())->html('<a href="/contact">Contact</a>');
        $this->assertStringContainsString('href="/contact"', $out);
    }

    public function testTextStripsAllMarkup(): void
    {
        $this->assertSame('Hello < World', (new Sanitizer())->text('<b>Hello</b> &lt; World'));
    }
}
