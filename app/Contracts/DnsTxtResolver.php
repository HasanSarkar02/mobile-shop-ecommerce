<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Dns\DnsTxtLookupResult;

interface DnsTxtResolver
{
    public function lookup(string $hostname, string $recordName): DnsTxtLookupResult;
}
