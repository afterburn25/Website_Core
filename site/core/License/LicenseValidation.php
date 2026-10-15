<?php
declare(strict_types=1);

namespace Core\License;

use Core\Config;
use Core\Database\Connection;

/**
 * License validation service for phone-home DRM system.
 *
 * Validates license keys against the Cajunwebpro.com API, stores
 * validation state, and handles deactivation.
 */
final class LicenseValidation
{
    private const TIMEOUT = 10;

    public function __construct(
        private Connection $db,
        private Config $config,
    ) {}

    /**
     * Get the API base URL from config.
     */
    private function apiBase(): string
    {
        return rtrim((string) $this->config->get('license.api_url', 'https://cajunwebpro.com/api'), '/');
    }

    /**
     * Validate a license key for the current domain.
     *
     * @param string $licenseKey The license key from the bundled module
     * @param string $domain The current domain (detected from request)
     * @return array{0:bool,1:?string} [valid, error_message]
     */
    public function validate(string $licenseKey, string $domain): array
    {
        $endpoint = $this->apiBase() . '/validate.php';
        $payload = json_encode([
            'license_key' => $licenseKey,
            'domain' => $domain,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                            "User-Agent: Website-Core-License/1.0\r\n",
                'content' => $payload,
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $context);
        if ($response === false) {
            $this->recordValidation($licenseKey, $domain, false, 'API request failed: ' . (error_get_last()['message'] ?? 'unknown'));
            return [false, 'Could not contact license server. Please check your internet connection.'];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->recordValidation($licenseKey, $domain, false, 'Invalid API response');
            return [false, 'Invalid response from license server.'];
        }

        $valid = (bool) ($data['authorized'] ?? false);
        $error = $data['error'] ?? null;

        $this->recordValidation($licenseKey, $domain, $valid, $error);

        if (!$valid) {
            return [false, $error ?? 'License validation failed.'];
        }

        return [true, null];
    }

    /**
     * Check if the current license is valid and not deactivated.
     *
     * @return bool True if license is valid
     */
    public function isValid(): bool
    {
        $row = $this->db->fetch(
            'SELECT license_valid, license_deactivated FROM `' . $this->db->table('license') . '` LIMIT 1'
        );
        if (!$row) {
            return false;
        }
        return (bool) $row['license_valid'] && !(bool) $row['license_deactivated'];
    }

    /**
     * Store a license key and domain in the database.
     */
    public function storeLicenseKey(string $licenseKey, string $domain): void
    {
        $table = $this->db->table('license');
        $this->db->run(
            "INSERT INTO `{$table}` (license_key, license_domain, created_at)
             VALUES (?, ?, ?)",
            [$licenseKey, $domain, date('Y-m-d H:i:s')]
        );
    }

    /**
     * Get the current license status for display.
     *
     * @return array{license_key:?string,license_domain:?string,license_valid:?int,license_validated_at:?string,license_error:?string}
     */
    public function status(): array
    {
        $row = $this->db->fetch(
            'SELECT license_key, license_domain, license_valid, license_validated_at, license_error FROM `'
            . $this->db->table('license') . '` ORDER BY created_at DESC LIMIT 1'
        );
        if (!$row) {
            return ['license_key' => null];
        }
        return $row;
    }

    /**
     * Mark the license as deactivated (remote deactivation).
     */
    public function deactivate(): void
    {
        $this->db->run(
            'UPDATE `' . $this->db->table('license') . '` SET license_deactivated = 1, license_error = ?',
            ['License deactivated by Cajunwebpro.com.']
        );
    }

    /**
     * Record a validation attempt in the database.
     */
    private function recordValidation(string $licenseKey, string $domain, bool $valid, ?string $error): void
    {
        $now = date('Y-m-d H:i:s');
        $table = $this->db->table('license');

        // First try to insert
        try {
            $this->db->run(
                "INSERT INTO `{$table}`
                 (license_key, license_domain, license_valid, license_validated_at, license_error)
                 VALUES (?, ?, ?, ?, ?)",
                [$licenseKey, $domain, $valid ? 1 : 0, $now, $error]
            );
        } catch (\Throwable $e) {
            // If duplicate key, update instead
            $this->db->run(
                "UPDATE `{$table}`
                 SET license_valid = ?, license_validated_at = ?, license_error = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE license_key = ?",
                [$valid ? 1 : 0, $now, $error, $licenseKey]
            );
        }
    }
}
