<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Support\Semver;
use PHPUnit\Framework\TestCase;

final class SemverTest extends TestCase
{
    public function testValid(): void
    {
        $this->assertTrue(Semver::valid('1.2.3'));
        $this->assertTrue(Semver::valid('1.2.3-rc.1'));
        $this->assertTrue(Semver::valid('0.1.0-dev+build5'));
        $this->assertFalse(Semver::valid('1.2'));
        $this->assertFalse(Semver::valid('v1.2.3'));
        $this->assertFalse(Semver::valid('1.2.3.4'));
        $this->assertFalse(Semver::valid(''));
    }

    public function testCompare(): void
    {
        $this->assertSame(0, Semver::compare('1.2.3', '1.2.3'));
        $this->assertSame(-1, Semver::compare('1.2.3', '1.2.4'));
        $this->assertSame(1, Semver::compare('2.0.0', '1.9.9'));
        $this->assertSame(-1, Semver::compare('1.0.0-alpha', '1.0.0'));
        $this->assertSame(1, Semver::compare('1.0.0', '1.0.0-alpha'));
    }

    public function testSatisfies(): void
    {
        $this->assertTrue(Semver::satisfies('1.2.3', '1.2.3'));
        $this->assertFalse(Semver::satisfies('1.2.4', '1.2.3'));
        $this->assertTrue(Semver::satisfies('1.5.0', '>=1.2.0'));
        $this->assertTrue(Semver::satisfies('1.5.0', '>=1.2.0,<2.0.0'));
        $this->assertFalse(Semver::satisfies('2.0.0', '>=1.2.0,<2.0.0'));
        $this->assertTrue(Semver::satisfies('1.4.9', '^1.2.0'));
        $this->assertFalse(Semver::satisfies('2.0.0', '^1.2.0'));
        $this->assertTrue(Semver::satisfies('0.2.9', '^0.2.0'));
        $this->assertFalse(Semver::satisfies('0.3.0', '^0.2.0'));
        $this->assertTrue(Semver::satisfies('1.2.9', '~1.2.0'));
        $this->assertFalse(Semver::satisfies('1.3.0', '~1.2.0'));
        $this->assertTrue(Semver::satisfies('1.2.7', '1.2.*'));
        $this->assertFalse(Semver::satisfies('1.3.0', '1.2.*'));
        $this->assertTrue(Semver::satisfies('9.9.9', '*'));
    }
}
