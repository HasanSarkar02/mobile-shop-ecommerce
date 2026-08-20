<?php

declare(strict_types=1);

namespace App\Enums;

enum DeploymentMode: string
{
    case SaaS = 'saas';
    case Dedicated = 'dedicated';
}
