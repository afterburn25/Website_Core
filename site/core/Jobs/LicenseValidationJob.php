<?php
declare(strict_types=1);

namespace Core\Jobs;

use Core\Config;
use Core\Database\Connection;
use Core\License\LicenseValidation;

/**
 * Periodic license validation job.
 *
 * Runs via cron to check license status with Cajunwebpro.com.
 * If validation fails or license is deactivated, the site enters a
 * degraded state (read-only or blocked).
 */
final class LicenseValidationJob
{
    public function __construct(
        private Connection $db,
        private Config $config,
    ) {}

    /**
     * Execute the license validation check.
     *
     * @param array $payload Job payload (empty for this job)
     * @return bool True if successful, false if should retry
     */
    public function handle(array $payload): bool
    {
        $license = $this->db->fetch(
            'SELECT license_key, license_domain FROM `' . $this->db->table('license')
            . '` ORDER BY created_at DESC LIMIT 1'
        );
        if (!$license) {
            // No license found - this may be a dev install
            return true;
        }

        $validator = new LicenseValidation($this->db, $this->config);
        // Validate the domain this install actually declares (app.url) —
        // a site moved to an unregistered host self-denies rather than
        // passing on its stale stored domain. Fall back to the stored
        // domain if config yields nothing.
        $domain = $validator->detectDomain();
        if ($domain === '') {
            $domain = (string) $license['license_domain'];
        }
        [$valid, $error, $denied] = $validator->validate(
            (string) $license['license_key'],
            $domain
        );

        if (!$valid) {
            error_log('[website-core] License validation failed: ' . ($error ?? 'unknown'));
            if ($denied) {
                // Only a definitive server denial (deactivated license or
                // unregistered domain) deactivates the site — network
                // failures and malformed responses leave it running and
                // retry on the next scheduled validation.
                $validator->deactivate();
            }
        }

        return true;
    }
}
