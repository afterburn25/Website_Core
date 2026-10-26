<?php
declare(strict_types=1);

namespace Core\Commerce;

use Core\Modules\ModuleRegistry;

final class CommerceFeatures
{
    public function __construct(private ModuleRegistry $registry) {}

    public function enabled(string $surface): bool
    {
        return $this->registry->supportsFeature('commerce.' . $surface);
    }

    public function provider(string $surface): ?string
    {
        return $this->registry->feature('commerce.' . $surface)['package'] ?? null;
    }

    public function options(string $surface): array
    {
        return $this->registry->feature('commerce.' . $surface)['options'] ?? [];
    }

    public function currency(): string
    {
        return (string) ($this->options('catalog')['currency'] ?? 'USD');
    }

    public function fulfillment(): array
    {
        return $this->options('checkout')['fulfillment'] ?? ['pickup'];
    }

    public function storeName(): string
    {
        return (string) ($this->options('catalog')['store_name'] ?? 'Store');
    }
}
