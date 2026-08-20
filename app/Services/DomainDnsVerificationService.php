<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DnsTxtResolver;
use App\Enums\DeploymentMode;
use App\Enums\DnsTxtLookupStatus;
use App\Enums\DomainStatus;
use App\Jobs\CheckDomainDnsVerification;
use App\Models\Domain;
use App\Models\Tenant;
use App\Support\Dns\DnsTxtLookupResult;
use App\Support\Dns\DomainDnsVerificationResult;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class DomainDnsVerificationService
{
    public function __construct(
        private readonly DnsTxtResolver $resolver,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function dispatchCheck(Domain $domain): bool
    {
        if (config('deployment.mode') === DeploymentMode::Dedicated->value) {
            return false;
        }

        $tenant = Tenant::query()->find($domain->getAttribute('tenant_id'));

        if ($tenant === null || ! $this->subscriptions->canUseCustomDomain($tenant)) {
            return false;
        }

        if ($this->statusOf($domain) !== DomainStatus::Pending) {
            return false;
        }

        $digest = $domain->getAttribute('verification_token_digest');
        $expiresAt = $domain->getAttribute('verification_expires_at');

        if (! is_string($digest) || $digest === '' || ! $expiresAt instanceof CarbonInterface || $expiresAt->isPast()) {
            return false;
        }

        $key = 'domain-dns-check:'.$domain->id.':'.$digest;
        $cooldown = max(1, (int) config('domain-verification.manual_check_cooldown_seconds', 30));

        if (! Cache::add($key, true, $cooldown)) {
            return false;
        }

        CheckDomainDnsVerification::dispatch((int) $domain->id, $digest)->afterCommit();

        return true;
    }

    public function check(int $domainId, string $expectedDigest): DomainDnsVerificationResult
    {
        $domain = Domain::query()->find($domainId);

        if ($domain === null) {
            return new DomainDnsVerificationResult(false, false, 'domain_not_found', 'The domain no longer exists.');
        }

        if (config('deployment.mode') === DeploymentMode::Dedicated->value) {
            return new DomainDnsVerificationResult(false, false, 'dedicated_mode', 'SaaS DNS verification is unavailable in Dedicated mode.');
        }

        $tenant = Tenant::query()->find($domain->getAttribute('tenant_id'));

        if ($tenant === null || ! $this->subscriptions->canUseCustomDomain($tenant)) {
            return new DomainDnsVerificationResult(false, false, 'not_entitled', 'The tenant is not entitled to use custom domains.');
        }

        $this->log($domain, 'domain.verification_check_started', [
            'expected_digest' => hash('sha256', $expectedDigest),
        ]);

        $lookup = $this->resolver->lookup(
            (string) $domain->getAttribute('domain'),
            (string) $domain->getAttribute('verification_record_name'),
        );

        return DB::transaction(function () use ($domainId, $expectedDigest, $lookup): DomainDnsVerificationResult {
            $locked = Domain::query()->whereKey($domainId)->lockForUpdate()->first();

            if ($locked === null) {
                return new DomainDnsVerificationResult(false, false, 'domain_not_found', 'The domain no longer exists.');
            }

            $currentDigest = $locked->getAttribute('verification_token_digest');

            if (! is_string($currentDigest) || ! hash_equals($currentDigest, $expectedDigest)) {
                $this->log($locked, 'domain.verification_check_stale', [
                    'reason' => 'The challenge changed while the DNS check was running.',
                ]);

                return new DomainDnsVerificationResult(false, false, 'stale_challenge', 'The verification challenge has changed.');
            }

            if ($this->statusOf($locked) !== DomainStatus::Pending) {
                return new DomainDnsVerificationResult(false, false, 'domain_not_pending', 'The domain is no longer pending verification.');
            }

            $expiresAt = $locked->getAttribute('verification_expires_at');

            if (! $expiresAt instanceof CarbonInterface || $expiresAt->isPast()) {
                return $this->recordFailure(
                    $locked,
                    'challenge_expired',
                    'The verification challenge has expired.',
                    false,
                );
            }

            if ($lookup->status === DnsTxtLookupStatus::RecordsFound) {
                foreach ($lookup->values as $value) {
                    if (hash_equals($currentDigest, hash('sha256', trim($value)))) {
                        $locked->update([
                            'status' => DomainStatus::Verified,
                            'verified_at' => now(),
                            'last_checked_at' => now(),
                            'verification_attempts' => $locked->verification_attempts + 1,
                            'verification_failure_code' => null,
                            'verification_failure_message' => null,
                        ]);
                        $this->log($locked, 'domain.verified', [
                            'verification_method' => 'dns_txt',
                        ]);

                        return new DomainDnsVerificationResult(true, false);
                    }
                }

                return $this->recordFailure($locked, 'wrong_txt', 'No TXT record matched the current verification challenge.', false);
            }

            $failureCode = $this->failureCode($lookup);
            $failureMessage = $lookup->errorMessage ?? $this->defaultFailureMessage($lookup->status);
            $retryable = $lookup->status->isRetryable()
                && $lookup->status !== DnsTxtLookupStatus::NxDomain
                && ! in_array($lookup->errorCode, ['invalid_hostname', 'invalid_record_name'], true);
            $attemptsAfter = $locked->verification_attempts + 1;
            $maxAttempts = max(1, (int) config('domain-verification.max_attempts', 6));

            return $this->recordFailure(
                $locked,
                $failureCode,
                $failureMessage,
                $retryable && $attemptsAfter < $maxAttempts,
            );
        });
    }

    private function recordFailure(Domain $domain, string $code, string $message, bool $retryable): DomainDnsVerificationResult
    {
        $attemptsAfter = $domain->verification_attempts + 1;
        $maxAttempts = max(1, (int) config('domain-verification.max_attempts', 6));
        $willRetry = $retryable && $attemptsAfter < $maxAttempts;

        $domain->update([
            'status' => $willRetry ? DomainStatus::Pending : DomainStatus::Failed,
            'last_checked_at' => now(),
            'verification_attempts' => $attemptsAfter,
            'verification_failure_code' => $code,
            'verification_failure_message' => $message,
        ]);

        $this->log($domain, 'domain.verification_failed', [
            'failure_code' => $code,
            'failure_message' => $message,
            'retryable' => $willRetry,
        ]);

        return new DomainDnsVerificationResult(
            false,
            $willRetry,
            $code,
            $message,
            $willRetry ? $this->retryAfterSeconds($attemptsAfter) : 0,
        );
    }

    private function failureCode(DnsTxtLookupResult $lookup): string
    {
        return match ($lookup->status) {
            DnsTxtLookupStatus::Missing => 'missing_txt',
            DnsTxtLookupStatus::Empty => 'empty_txt',
            DnsTxtLookupStatus::NxDomain => 'nxdomain',
            DnsTxtLookupStatus::ServFail => 'servfail',
            DnsTxtLookupStatus::TemporaryFailure => 'temporary_dns_failure',
            DnsTxtLookupStatus::Error => $lookup->errorCode ?? 'resolver_error',
            DnsTxtLookupStatus::RecordsFound => 'wrong_txt',
        };
    }

    private function defaultFailureMessage(DnsTxtLookupStatus $status): string
    {
        return match ($status) {
            DnsTxtLookupStatus::Missing => 'No TXT record was found yet.',
            DnsTxtLookupStatus::Empty => 'The TXT response did not contain a usable value.',
            DnsTxtLookupStatus::NxDomain => 'The verification record name does not exist.',
            DnsTxtLookupStatus::ServFail => 'The DNS resolver reported a temporary server failure.',
            DnsTxtLookupStatus::TemporaryFailure, DnsTxtLookupStatus::Error => 'The DNS resolver did not return a reliable result.',
            DnsTxtLookupStatus::RecordsFound => 'No TXT record matched the current verification challenge.',
        };
    }

    private function retryAfterSeconds(int $attempt): int
    {
        $backoff = (array) config('domain-verification.retry_backoff_seconds', [30, 120, 300]);
        $base = (int) ($backoff[min(max(0, $attempt - 1), count($backoff) - 1)] ?? 300);

        return $base + random_int(0, max(1, (int) floor($base * 0.2)));
    }

    private function statusOf(Domain $domain): DomainStatus
    {
        $status = $domain->getAttribute('status');

        return $status instanceof DomainStatus
            ? $status
            : DomainStatus::tryFrom((string) $status)
                ?? throw new DomainException('Domain has an invalid lifecycle status.');
    }

    /** @param array<string, mixed> $properties */
    private function log(Domain $domain, string $event, array $properties = []): void
    {
        activity('domains')
            ->performedOn($domain)
            ->causedByAnonymous()
            ->event($event)
            ->withProperties(array_merge([
                'domain_id' => $domain->id,
                'tenant_id' => $domain->getAttribute('tenant_id'),
            ], $properties))
            ->log($event);
    }
}
