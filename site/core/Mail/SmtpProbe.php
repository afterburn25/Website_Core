<?php
declare(strict_types=1);

namespace Core\Mail;

/** Encrypted SMTP discovery only: never authenticates or transmits a message. */
final class SmtpProbe
{
    /** @return array{ok:bool,message:string} */
    public function check(string $host, string $address, int $port, string $secure, float $timeout): array
    {
        if (!filter_var($address, FILTER_VALIDATE_IP) || !in_array($secure, ['ssl', 'tls'], true)) {
            return $this->failed('Invalid diagnostic target.');
        }
        $deadline = microtime(true) + max(0.01, $timeout);
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $host,
            'SNI_enabled' => true,
            'SNI_server_name' => $host,
        ]]);
        $target = str_contains($address, ':') ? '[' . $address . ']' : $address;
        $socket = @stream_socket_client('tcp://' . $target . ':' . $port, $errno, $error,
            max(0.01, $timeout), STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) return $this->failed('TCP connection refused or timed out.');

        try {
            if ($secure === 'ssl' && !$this->encrypt($socket, $deadline)) {
                return $this->failed('TLS negotiation or certificate verification failed, or timed out.');
            }
            if ($this->reply($socket, $deadline)[0] !== 220) {
                return $this->failed('No valid SMTP greeting before the timeout.');
            }
            if (!$this->command($socket, "EHLO diagnostic.invalid\r\n", $deadline)) {
                return $this->failed('SMTP greeting timed out.');
            }
            [$code, $extensions] = $this->reply($socket, $deadline);
            if ($code !== 250) return $this->failed('The server did not accept SMTP EHLO.');
            if ($secure === 'tls') {
                if (!preg_match('/^250[ -]STARTTLS\b/im', $extensions)) {
                    return $this->failed('This port does not advertise STARTTLS.');
                }
                if (!$this->command($socket, "STARTTLS\r\n", $deadline)
                    || $this->reply($socket, $deadline)[0] !== 220
                    || !$this->encrypt($socket, $deadline)) {
                    return $this->failed('STARTTLS negotiation or certificate verification failed, or timed out.');
                }
                if (!$this->command($socket, "EHLO diagnostic.invalid\r\n", $deadline)
                    || $this->reply($socket, $deadline)[0] !== 250) {
                    return $this->failed('Encrypted SMTP greeting failed or timed out.');
                }
            }
            return ['ok' => true, 'message' => 'Encrypted SMTP accepted; certificate verified. Authentication has not been tested.'];
        } catch (\Throwable) {
            // Never return server banners, certificate subjects, or socket errors.
            return $this->failed('The encrypted SMTP probe could not finish.');
        } finally {
            fclose($socket);
        }
    }

    private function encrypt($socket, float $deadline): bool
    {
        return $this->arm($socket, $deadline)
            && @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === true;
    }

    private function command($socket, string $command, float $deadline): bool
    {
        return $this->arm($socket, $deadline) && @fwrite($socket, $command) === strlen($command);
    }

    /** Bounded multiline reply; raw text is used only for STARTTLS detection. */
    private function reply($socket, float $deadline): array
    {
        $body = ''; $code = null;
        for ($line = 0; $line < 30 && strlen($body) < 8192; $line++) {
            if (!$this->arm($socket, $deadline)) break;
            $part = @fgets($socket, 513);
            if (!is_string($part) || !preg_match('/^(\d{3})([ -]).*\r?\n$/', $part, $match)) break;
            if ($code !== null && $code !== (int) $match[1]) break;
            $code = (int) $match[1]; $body .= $part;
            if ($match[2] === ' ') return [$code, $body];
        }
        return [null, ''];
    }

    private function arm($socket, float $deadline): bool
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) return false;
        $seconds = (int) $remaining;
        return stream_set_timeout($socket, $seconds, max(1, (int) (($remaining - $seconds) * 1000000)));
    }

    private function failed(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
