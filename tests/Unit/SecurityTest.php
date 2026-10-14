<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Application;
use Core\Http\Request;
use Core\Security\Csrf;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testCsrfLifecycle(): void
    {
        $csrf = new Csrf();
        $token = $csrf->token();
        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $csrf->token()); // stable per session

        $req = new Request('POST', '/x', '', [], ['_csrf' => $token], [], [], []);
        $this->assertTrue($csrf->validate($req));

        $bad = new Request('POST', '/x', '', [], ['_csrf' => 'wrong'], [], [], []);
        $this->assertFalse($csrf->validate($bad));

        $missing = new Request('POST', '/x', '', [], [], [], [], []);
        $this->assertFalse($csrf->validate($missing));
    }

    public function testCsrfFieldIsEscaped(): void
    {
        $csrf = new Csrf();
        $field = $csrf->field();
        $this->assertStringContainsString('name="_csrf"', $field);
        $this->assertStringContainsString($csrf->token(), $field);
    }

    public function testIpInCidr(): void
    {
        $this->assertTrue(Application::ipInCidr('10.0.0.5', '10.0.0.0/8'));
        $this->assertTrue(Application::ipInCidr('192.168.1.9', '192.168.1.0/24'));
        $this->assertFalse(Application::ipInCidr('192.168.2.9', '192.168.1.0/24'));
        $this->assertTrue(Application::ipInCidr('1.2.3.4', '1.2.3.4'));
        $this->assertFalse(Application::ipInCidr('1.2.3.5', '1.2.3.4'));
        $this->assertTrue(Application::ipInCidr('2001:db8::1', '2001:db8::/32'));
        $this->assertFalse(Application::ipInCidr('2001:db9::1', '2001:db8::/32'));
        $this->assertFalse(Application::ipInCidr('not-an-ip', '10.0.0.0/8'));
    }

    public function testEurlBlocksJavascript(): void
    {
        $this->assertSame('#', e_url('javascript:alert(1)'));
        $this->assertSame('#', e_url('data:text/html;base64,x'));
        $this->assertSame('https://ok.test/', e_url('https://ok.test/'));
        $this->assertSame('/relative/path', e_url('/relative/path'));
    }
}
