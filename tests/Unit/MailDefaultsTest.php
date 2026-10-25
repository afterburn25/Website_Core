<?php
declare(strict_types=1);
namespace Tests\Unit;
use Core\Mail\MailDefaults;
use PHPUnit\Framework\TestCase;

final class MailDefaultsTest extends TestCase
{
    public function testSubdomainInstallUsesBusinessRootDomain(): void
    {
        $defaults=MailDefaults::fromUrl('https://demo.cajunwebpro.com/catering/');
        self::assertSame('cajunwebpro.com',$defaults['domain']);
        self::assertSame('smtp',$defaults['transport']);
        self::assertSame('mail.cajunwebpro.com',$defaults['host']);
        self::assertSame('admin@cajunwebpro.com',$defaults['from']);
        self::assertSame('ssl',$defaults['secure']);
        self::assertSame(465,$defaults['port']);
    }

    public function testCommonMultiPartPublicSuffixKeepsRegistrantLabel(): void
    {
        self::assertSame('example.co.uk',MailDefaults::fromUrl('https://shop.example.co.uk')['domain']);
    }
}
