<?php
declare(strict_types=1);

// CLI scaffold: php sdk/scaffold.php <industry|addon|theme> <publisher/name> <dest-dir>
require __DIR__ . '/src/Scaffold.php';

[$script, $type, $id, $dest] = [$argv[0] ?? '', $argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
if ($type === '' || $id === '' || $dest === '') {
    fwrite(STDERR, "usage: php sdk/scaffold.php <industry|addon|theme> <publisher/name> <dest-dir>\n");
    exit(2);
}
try {
    \WebsiteCore\Sdk\Scaffold::make($type, $id, $dest);
    echo "created {$id} ({$type}) in {$dest}\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "error: {$e->getMessage()}\n");
    exit(1);
}
