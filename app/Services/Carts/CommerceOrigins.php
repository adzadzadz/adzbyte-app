<?php

namespace App\Services\Carts;

final class CommerceOrigins
{
    public static function app(): string
    {
        return self::validate(config('app.url'));
    }

    public static function storefront(): string
    {
        return self::validate(config('commerce.storefront_origin'));
    }

    private static function validate(mixed $origin): string
    {
        $parts = is_string($origin) ? parse_url($origin) : false;
        if (! is_array($parts) || ! isset($parts['host'], $parts['scheme'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || ! (strtolower($parts['scheme']) === 'https' || (strtolower($parts['scheme']) === 'http' && in_array(strtolower($parts['host']), ['127.0.0.1', 'localhost', '[::1]'], true)))
            || ! filter_var($origin, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Configure a trusted root commerce origin.');
        }

        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? null;

        return $scheme.'://'.strtolower($parts['host']).($port === null || ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80) ? '' : ':'.$port);
    }
}
