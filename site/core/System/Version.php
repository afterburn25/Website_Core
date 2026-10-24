<?php
declare(strict_types=1);

namespace Core\System;

use Core\Modules\ModuleRegistry;

/** Single authoritative runtime version inventory for admin and diagnostics. */
final class Version
{
    public function __construct(private ModuleRegistry $registry) {}

    public function core(): string
    {
        return defined('WC_VERSION') ? WC_VERSION : 'development';
    }

    /** @return list<array{id:string,name:string,version:string}> */
    public function industries(): array
    {
        $versions = [];
        foreach ($this->registry->all() as $id => $info) {
            $manifest = $info['manifest'] ?? [];
            if (($manifest['type'] ?? null) !== 'industry' || ($info['state'] ?? null) !== 'enabled') {
                continue;
            }
            $versions[] = [
                'id' => (string) $id,
                'name' => (string) ($manifest['name'] ?? $id),
                'version' => (string) ($manifest['version'] ?? 'unknown'),
            ];
        }
        usort($versions, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        return $versions;
    }
}
