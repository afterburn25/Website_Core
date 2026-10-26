<?php
declare(strict_types=1);

// One loopback connection, never a real mailbox or external SMTP service.
[$script, $mode, $certificate, $log] = $argv;
$context = stream_context_create(['ssl' => ['local_cert' => $certificate]]);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
echo stream_socket_get_name($server, false) . "\n";
flush();
$client = @stream_socket_accept($server, 3);
if (!is_resource($client)) exit(1);
stream_set_timeout($client, 2);
if ($mode === 'silent') {
    usleep(700000);
    fclose($client); fclose($server); exit;
}
if ($mode === 'ssl' && @stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) exit;
@fwrite($client, "220 fixture.invalid ESMTP\r\n");
while (is_string($command = @fgets($client, 513))) {
    file_put_contents($log, $command, FILE_APPEND);
    if (str_starts_with($command, 'EHLO ')) {
        @fwrite($client, $mode === 'plain' ? "250 fixture.invalid\r\n" : "250-fixture.invalid\r\n250 STARTTLS\r\n");
    } elseif ($command === "STARTTLS\r\n") {
        @fwrite($client, "220 Begin TLS\r\n");
        if (@stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) break;
    } else {
        @fwrite($client, "500 Unexpected command\r\n");
    }
}
fclose($client); fclose($server);
