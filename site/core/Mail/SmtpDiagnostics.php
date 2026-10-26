<?php
declare(strict_types=1);

namespace Core\Mail;

/** Forward-confirmed provider discovery, separate from saved/authenticated transport. */
final class SmtpDiagnostics
{
    private \Closure $resolve;
    private \Closure $reverse;
    private \Closure $probe;
    private \Closure $clock;

    public function __construct(?callable $resolve = null, ?callable $reverse = null,
        ?callable $probe = null, ?callable $clock = null)
    {
        $this->resolve = \Closure::fromCallable($resolve ?? self::addresses(...));
        $this->reverse = \Closure::fromCallable($reverse ?? static fn (string $ip) => @gethostbyaddr($ip));
        $this->probe = \Closure::fromCallable($probe ?? (new SmtpProbe())->check(...));
        $this->clock = \Closure::fromCallable($clock ?? static fn (): float => microtime(true));
    }

    /** No credentials accepted, no settings or audit stores accessed. */
    public function inspect(string $host): array
    {
        $host = strtolower(rtrim(trim($host), '.'));
        $result = ['ok' => false, 'message' => '', 'checks' => [], 'provider_host' => null];
        if (!self::hostOk($host)) {
            $result['message'] = 'Enter a valid SMTP hostname or IP address.';
            return $result;
        }
        $deadline = ($this->clock)() + 8.0;
        try {
            $addresses = $this->validAddresses(($this->resolve)($host));
            if ($addresses === []) {
                $result['message'] = 'DNS lookup failed. Use the exact outgoing-mail hostname from your hosting panel.';
                return $result;
            }
            $targets = [$host => $addresses[0]];
            if (($this->clock)() < $deadline) {
                $reverse = ($this->reverse)($addresses[0]);
                $candidate = is_string($reverse) ? strtolower(rtrim($reverse, '.')) : '';
                if ($candidate !== $host && self::hostOk($candidate)
                    && !filter_var($candidate, FILTER_VALIDATE_IP) && ($this->clock)() < $deadline) {
                    $forward = $this->validAddresses(($this->resolve)($candidate));
                    // Pin the subsequent socket to the original address. A second DNS
                    // resolution must not redirect this probe to a different server.
                    if (in_array(inet_pton($addresses[0]), array_map('inet_pton', $forward), true)) {
                        $targets[$candidate] = $addresses[0];
                        $result['provider_host'] = $candidate;
                    }
                }
            }
            foreach ($targets as $name => $ip) {
                foreach (['ssl' => 465, 'tls' => 587] as $secure => $port) {
                    $remaining = $deadline - ($this->clock)();
                    $check = $remaining <= 0
                        ? ['ok' => false, 'message' => 'Not tested: diagnostic time budget exhausted.']
                        : ($this->probe)($name, $ip, $port, $secure, min(1.5, $remaining));
                    $result['checks'][] = ['host' => $name, 'port' => $port, 'secure' => $secure] + $check;
                }
            }
            foreach ($result['checks'] as $check) {
                if (!$check['ok']) continue;
                $result['ok'] = true;
                $result['message'] = 'Encrypted SMTP is available at ' . $check['host'] . ' using '
                    . ($check['secure'] === 'ssl' ? 'SSL/465' : 'STARTTLS/587')
                    . '. Enter these values and test authentication before saving. No credentials or email were sent; nothing was saved.';
                return $result;
            }
            $result['message'] = 'Neither standard encrypted SMTP pair could be verified.'
                . ($result['provider_host'] ? ' Forward-confirmed server hostname: ' . $result['provider_host'] . '.' : '')
                . ' Review the results and confirm outgoing-mail settings with your host. No credentials or email were sent; nothing was saved.';
        } catch (\Throwable) {
            $result['message'] = 'DNS or SMTP diagnostics are unavailable on this host. Confirm outgoing-mail settings with the provider.';
        }
        return $result;
    }

    public static function hostOk(string $host): bool
    {
        return $host !== '' && strlen($host) <= 253 && (filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false);
    }

    private function validAddresses(mixed $addresses): array
    {
        return is_array($addresses) ? array_values(array_filter($addresses,
            static fn ($ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false)) : [];
    }

    private static function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) return [$host];
        $addresses = @gethostbynamel($host) ?: [];
        if ($addresses === [] && function_exists('dns_get_record')) {
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) $addresses[] = $record['ipv6'];
            }
        }
        return $addresses;
    }
}
