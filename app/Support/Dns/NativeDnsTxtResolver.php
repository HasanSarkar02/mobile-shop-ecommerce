<?php

declare(strict_types=1);

namespace App\Support\Dns;

use App\Contracts\DnsTxtResolver;
use App\Enums\DnsTxtLookupStatus;
use App\Support\Tenancy\DomainHostname;
use Throwable;

final class NativeDnsTxtResolver implements DnsTxtResolver
{
    /** @var (\Closure(string, int): (array<int, array<string, mixed>>|false))|null */
    private readonly ?\Closure $lookup;

    /**
     * The optional callable keeps native DNS behavior deterministic in tests;
     * production uses dns_get_record() directly.
     *
     * @param  (\Closure(string, int): (array<int, array<string, mixed>>|false))|null  $lookup
     */
    public function __construct(?\Closure $lookup = null)
    {
        $this->lookup = $lookup;
    }

    public function lookup(string $hostname, string $recordName): DnsTxtLookupResult
    {
        try {
            $normalized = DomainHostname::normalize($hostname);
        } catch (Throwable $exception) {
            return DnsTxtLookupResult::failure(
                DnsTxtLookupStatus::Error,
                'invalid_hostname',
                $exception->getMessage(),
            );
        }

        $prefix = rtrim((string) config('domain-verification.record_prefix', '_mobile-shop-verification'), '.');
        $expectedRecordName = $prefix.'.'.$normalized;

        if (strtolower(rtrim(trim($recordName), '.')) !== strtolower($expectedRecordName)) {
            return DnsTxtLookupResult::failure(
                DnsTxtLookupStatus::Error,
                'invalid_record_name',
                'The verification record name does not match the configured hostname.',
            );
        }

        $warning = null;
        $records = false;

        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });

        try {
            $lookup = $this->lookup ?? static fn (string $name, int $type): array|false => dns_get_record($name, $type);
            $records = $lookup($expectedRecordName, DNS_TXT);
        } catch (Throwable $exception) {
            restore_error_handler();

            return DnsTxtLookupResult::failure(
                DnsTxtLookupStatus::Error,
                'resolver_exception',
                $exception->getMessage(),
            );
        }

        restore_error_handler();

        if ($records === false) {
            $message = $warning ?: 'The DNS resolver failed to return a result.';
            $lowerMessage = strtolower($message);
            $status = str_contains($lowerMessage, 'nxdomain')
                || str_contains($lowerMessage, 'name error')
                || str_contains($lowerMessage, 'non-existent')
                ? DnsTxtLookupStatus::NxDomain
                : (str_contains($lowerMessage, 'servfail')
                    ? DnsTxtLookupStatus::ServFail
                    : DnsTxtLookupStatus::TemporaryFailure);

            return DnsTxtLookupResult::failure($status, $status->value, $message);
        }

        if ($records === []) {
            return DnsTxtLookupResult::missing();
        }

        $values = [];

        foreach ($records as $record) {
            $chunks = $record['entries'] ?? null;

            if (is_array($chunks)) {
                $value = implode('', array_map(static fn (mixed $chunk): string => (string) $chunk, $chunks));
            } else {
                $value = (string) ($record['txt'] ?? '');
            }

            $value = trim($value);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values === [] ? DnsTxtLookupResult::empty() : DnsTxtLookupResult::records($values);
    }
}
