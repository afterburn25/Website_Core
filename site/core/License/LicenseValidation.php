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
     * @return array{0:bool,1:?string,2:bool} [valid, error, explicit-denial]
     *         The third element is true only when the server returned a
     *         definitive denial (HTTP 200 + authorized:false) — the only
     *         outcome that may deactivate the site.
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
            $this->recordValidation($licenseKey, $domain, null, 'API request failed: ' . (error_get_last()['message'] ?? 'unknown'));
            // Third return element: false = the server was not reached, so this
            // failure is inconclusive and must not deactivate the site.
            return [false, 'Could not contact license server. Please check your internet connection.', false];
        }

        // Only HTTP 200 responses carry a definitive verdict. 429 (rate
        // limit), 4xx (malformed request) and 5xx (server error) are
        // transient — the server could not evaluate the license, so the
        // result is inconclusive and must not deactivate the site.
        $statusCode = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $statusCode = (int) $m[1]; // last status line wins (redirects)
            }
        }
        if ($statusCode !== 200) {
            $this->recordValidation($licenseKey, $domain, null, 'License server returned HTTP ' . $statusCode);
            return [false, 'License server returned HTTP ' . $statusCode . '. Validation will retry automatically.', false];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->recordValidation($licenseKey, $domain, null, 'Invalid API response');
            return [false, 'Invalid response from license server.', false];
        }

        $valid = (bool) ($data['authorized'] ?? false);
        $error = $data['error'] ?? null;

        $this->recordValidation($licenseKey, $domain, $valid, $error);

        if (!$valid) {
            // The server explicitly denied this license/domain — a real denial.
            return [false, $error ?? 'License validation failed.', true];
        }

        return [true, null, false];
    }

    /**
     * Check if the current license is valid and not deactivated.
     *
     * @return bool True if license is valid
     */
    public function isValid(): bool
    {
        $row = $this->db->fetch(
            'SELECT license_valid, license_deactivated FROM `' . $this->db->table('license')
            . '` ORDER BY created_at DESC LIMIT 1'
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
     * The domain this install declares itself to serve — from the
     * canonical app.url the admin maintains. A site genuinely moved to a
     * new host gets its app.url updated, so validation then targets the
     * new domain and the install self-denies until that domain is
     * registered in the portal. HTTP_HOST is deliberately not used:
     * preview URLs, IP access and internal hostnames must never produce
     * a false denial.
     */
    public function detectDomain(): string
    {
        $host = (string) parse_url((string) $this->config->get('app.url', ''), PHP_URL_HOST);
        return strtolower((string) preg_replace('/:\d+$/', '', trim($host)));
    }

    /**
     * Get the current license status for display.
     *
     * @return array{license_key:?string,license_domain:?string,license_valid:?int,license_deactivated:?int,license_validated_at:?string,license_error:?string}
     */
    public function status(): array
    {
        $row = $this->db->fetch(
            'SELECT license_key, license_domain, license_valid, license_deactivated, license_validated_at, license_error FROM `'
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
        // Preserve the API's denial reason when one was recorded — it is
        // far more useful to the admin ("Domain not registered") than a
        // generic message.
        $this->db->run(
            'UPDATE `' . $this->db->table('license') . '` SET license_deactivated = 1,
             license_error = COALESCE(license_error, ?)',
            ['License deactivated by Cajunwebpro.com.']
        );
    }

    /**
     * Record a validation attempt. `$valid === null` means the outcome was
     * inconclusive (transport failure, non-200, malformed body) — the
     * stored verdict must be preserved so a transient outage cannot mark
     * an otherwise-valid license invalid and trip the public gate.
     */
    private function recordValidation(string $licenseKey, string $domain, ?bool $valid, ?string $error): void
    {
        $now = date('Y-m-d H:i:s');
        $table = $this->db->table('license');

        if ($valid === null) {
            // Inconclusive: log the attempt + error on an existing row,
            // preserving the stored verdict. Never INSERT here — a fresh
            // row would have license_key set with license_valid=0, which
            // the public gate reads as an invalid license.
            $this->db->run(
                "UPDATE `{$table}`
                 SET license_validated_at = ?, license_error = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE license_key = ?",
                [$now, $error, $licenseKey]
            );
            return;
        }

        // Definitive verdict: first try to insert, update on duplicate.
        try {
            $this->db->run(
                "INSERT INTO `{$table}`
                 (license_key, license_domain, license_valid, license_validated_at, license_error)
                 VALUES (?, ?, ?, ?, ?)",
                [$licenseKey, $domain, $valid ? 1 : 0, $now, $error]
            );
        } catch (\Throwable $e) {
            // If duplicate key, update instead — the stored domain tracks
            // what was actually validated last (app.url may have moved).
            $this->db->run(
                "UPDATE `{$table}`
                 SET license_valid = ?, license_domain = ?, license_validated_at = ?, license_error = ?,
                     license_deactivated = CASE WHEN ? THEN 0 ELSE license_deactivated END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE license_key = ?",
                [$valid ? 1 : 0, $domain, $now, $error, $valid ? 1 : 0, $licenseKey]
            );
        }
    }
}
