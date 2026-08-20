<?php

declare(strict_types=1);

namespace App\Enums;

enum DnsTxtLookupStatus: string
{
    case RecordsFound = 'records_found';
    case Missing = 'missing';
    case Empty = 'empty';
    case NxDomain = 'nxdomain';
    case ServFail = 'servfail';
    case TemporaryFailure = 'temporary_failure';
    case Error = 'error';

    public function isRetryable(): bool
    {
        return in_array($this, [self::Missing, self::Empty, self::ServFail, self::TemporaryFailure, self::Error], true);
    }
}
