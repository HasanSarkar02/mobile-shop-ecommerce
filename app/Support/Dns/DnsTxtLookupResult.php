<?php

declare(strict_types=1);

namespace App\Support\Dns;

use App\Enums\DnsTxtLookupStatus;

final readonly class DnsTxtLookupResult
{
    /** @param list<string> $values */
    public function __construct(
        public DnsTxtLookupStatus $status,
        public array $values = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    /** @param list<string> $values */
    public static function records(array $values): self
    {
        return new self(DnsTxtLookupStatus::RecordsFound, $values);
    }

    public static function missing(): self
    {
        return new self(DnsTxtLookupStatus::Missing);
    }

    public static function empty(): self
    {
        return new self(DnsTxtLookupStatus::Empty);
    }

    public static function failure(DnsTxtLookupStatus $status, string $code, string $message): self
    {
        return new self($status, [], $code, $message);
    }
}
