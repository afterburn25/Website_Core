<?php
/**
 * Mock of the Cajunwebpro.com license validation endpoint for tests.
 *
 * Mirrors the real contract implemented by Cajun_Web
 * site/api/handlers/license.php: JSON POST {license_key, domain} ->
 * {"authorized": bool, "error"?: string}.
 *
 * Behavior by license key prefix:
 *  - "GOOD-"      : authorized only when domain === 'e2e.example.com'
 *  - "DENIED-"    : always denied (explicit server denial)
 *  - "BROKEN-"    : returns malformed JSON
 *  - "RATELIMIT-" : HTTP 429 — transient, must not deactivate
 *  - "ERROR-"     : HTTP 500 — transient, must not deactivate
 *  - anything else: denied as an unknown license
 *
 * Every request payload is appended to the file named by E2E_LOG so the
 * test can assert the exact request shape sent by LicenseValidation.
 */
header('Content-Type: application/json');
$raw = file_get_contents('php://input');
if ($log = getenv('E2E_LOG')) {
    file_put_contents($log, $raw . "\n", FILE_APPEND | LOCK_EX);
}
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['license_key']) || empty($data['domain'])) {
    http_response_code(400);
    echo json_encode(['authorized' => false, 'error' => 'Invalid request']);
    exit;
}
$key = (string) $data['license_key'];
$domain = strtolower((string) $data['domain']);

if (str_starts_with($key, 'BROKEN-')) {
    echo 'this is not json';
    exit;
}
if (str_starts_with($key, 'RATELIMIT-')) {
    http_response_code(429);
    echo json_encode(['authorized' => false, 'error' => 'Rate limit exceeded']);
    exit;
}
if (str_starts_with($key, 'ERROR-')) {
    http_response_code(500);
    echo json_encode(['authorized' => false, 'error' => 'Internal server error']);
    exit;
}
if (str_starts_with($key, 'GOOD-')) {
    echo json_encode(
        $domain === 'e2e.example.com'
            ? ['authorized' => true]
            : ['authorized' => false, 'error' => 'Domain not registered']
    );
    exit;
}
echo json_encode(['authorized' => false, 'error' => 'License not found or deactivated']);
