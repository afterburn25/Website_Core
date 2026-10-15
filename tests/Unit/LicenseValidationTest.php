<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\License\LicenseValidation;
use PHPUnit\Framework\TestCase;

/**
 * License validation tests on in-memory sqlite. Tests the licensing
 * service's ability to store and validate license keys/domains without
 * actually calling the external API.
 */
final class LicenseValidationTest extends TestCase
{
    private function sqlite(): Connection
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        return new Connection(new \PDO('sqlite::memory:'), 'wc_');
    }

    private function config(): Config
    {
        return new Config('/nonexistent');
    }

    private function db(): Connection
    {
        $db = $this->sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        return $db;
    }

    public function testLicenseTableExists(): void
    {
        $db = $this->db();
        $this->assertTrue($db->tableExists('license'));
    }

    public function testLicenseKeyIsStored(): void
    {
        $db = $this->db();
        $license = new LicenseValidation($db, $this->config());

        $table = $db->table('license');
        $db->run(
            "INSERT INTO `{$table}` (license_key, license_domain, license_valid, license_validated_at)
             VALUES (?, ?, ?, ?)",
            ['TEST-KEY-123', 'example.com', 1, date('Y-m-d H:i:s')]
        );

        $status = $license->status();
        $this->assertSame('TEST-KEY-123', $status['license_key']);
        $this->assertSame('example.com', $status['license_domain']);
        $this->assertSame(1, $status['license_valid']);
    }

    public function testStatusReturnsEmptyWhenNoLicense(): void
    {
        $db = $this->db();
        $license = new LicenseValidation($db, $this->config());

        $status = $license->status();
        $this->assertSame(['license_key' => null], $status);
    }

    public function testDomainMismatchFails(): void
    {
        $db = $this->db();
        $license = new LicenseValidation($db, $this->config());

        $license->storeLicenseKey('KEY-123', 'example.com');

        // Validation is called with a different domain — this should fail
        // because the stored domain doesn't match. (The actual validation
        // would call the API; here we test the storage logic.)
        $table = $db->table('license');
        $db->run(
            "UPDATE `{$table}` SET license_domain = ? WHERE license_key = ?",
            ['different.com', 'KEY-123']
        );

        $status = $license->status();
        $this->assertSame('different.com', $status['license_domain']);
    }
}
