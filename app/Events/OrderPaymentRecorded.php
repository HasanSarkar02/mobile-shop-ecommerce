<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OrderPayment;
use Illuminate\Foundation\Events\Dispatchable;

class OrderPaymentRecorded
{
    use Dispatchable;

    public function __construct(public readonly OrderPayment $payment) {}
}
