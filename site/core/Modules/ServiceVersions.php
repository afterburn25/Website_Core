<?php
declare(strict_types=1);

namespace Core\Modules;

/** Stable names and semantic versions for optional Module API service contracts. */
final class ServiceVersions
{
    /** @return array<string,string> */
    public static function all(): array
    {
        return [
            'content.presentation' => '1.0.0',
            'forms.starter' => '1.0.0',
            'leads.quotes' => '2.0.0',
            'business.service_area' => '1.0.0',
            'appearance.collections' => '1.0.0',
            'commerce.checkout_validation' => '1.0.0',
        ];
    }

    public static function version(string $service): ?string
    {
        return self::all()[$service] ?? null;
    }
}
