<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderPaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\PreventsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use BelongsToTenant;
    use PreventsDeletion;

    protected $fillable = ['order_id', 'payment_method_id', 'amount', 'status', 'transaction_reference', 'paid_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => OrderPaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}