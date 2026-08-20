<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use InvalidArgumentException;

final class DomainHostname
{
    public static function normalize(string $hostname): string
    {
        $hostname = trim($hostname);

        if ($hostname === ''
            || preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $hostname)
            || preg_match('/[\/?#@:*]/', $hostname)
            || str_contains($hostname, '*')
        ) {
            throw new InvalidArgumentException('Enter a hostname without a scheme, path, query, port, or wildcard.');
        }

        $hostname = rtrim(strtolower($hostname), '.');

        if ($hostname === ''
            || $hostname === 'localhost'
            || str_ends_with($hostname, '.localhost')
            || str_ends_with($hostname, '.local')
            || str_ends_with($hostname, '.internal')
            || filter_var($hostname, FILTER_VALIDATE_IP) !== false
        ) {
            throw new InvalidArgumentException('The hostname must be a public DNS hostname.');
        }

        if (preg_match('/[^\x00-\x7F]/', $hostname)) {
            // Store IDNs in their ASCII/punycode form so uniqueness and host
            // resolution use one representation across browsers and DNS.
            if (! function_exists('idn_to_ascii')) {
                throw new InvalidArgumentException('Internationalized hostnames require the PHP intl extension.');
            }

            $ascii = idn_to_ascii($hostname, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii === false) {
                throw new InvalidArgumentException('The internationalized hostname is invalid.');
            }

            $hostname = strtolower($ascii);
        }

        $central = self::normalizeCentralDomain();

        if ($hostname === $central || str_ends_with($hostname, '.'.$central)) {
            throw new InvalidArgumentException('Central and platform hostnames cannot be custom domains.');
        }

        if (strlen($hostname) > 253
            || str_contains($hostname, '..')
            || ! str_contains($hostname, '.')
            || filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $hostname)
        ) {
            throw new InvalidArgumentException('The hostname format is invalid.');
        }

        return $hostname;
    }

    private static function normalizeCentralDomain(): string
    {
        return rtrim(strtolower(trim((string) config('tenancy.central_domain'))), '.');
    }
}
