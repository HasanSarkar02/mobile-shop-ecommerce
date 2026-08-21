<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ManualPaymentSubmission extends Component
{
    public Order $order;

    public string $transaction_reference = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $orderNumber): void
    {
        $order = Order::query()
            ->with(['paymentMethod', 'payments'])
            ->where('order_number', $orderNumber)
            ->first();

        abort_unless($order, 404);
        abort_unless($order->tenant_id === tenant()?->id, 404);

        $this->order = $order;
    }

    public function submit(OrderService $orders): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        if (RateLimiter::tooManyAttempts('manual-payment:'.request()->ip().':'.$this->order->id, 10)) {
            $this->errorMessage = 'Too many attempts. Please try again later.';

            return;
        }
        RateLimiter::hit('manual-payment:'.request()->ip().':'.$this->order->id, 60);

        $this->validate([
            'transaction_reference' => ['required', 'string', 'max:100'],
        ]);

        $reference = trim($this->transaction_reference);

        $method = $this->order->paymentMethod;

        if (! $method || ! $method->requires_verification) {
            $this->errorMessage = 'This order does not require manual payment verification.';

            return;
        }

        if ($this->order->payments()->where('transaction_reference', $reference)->exists()) {
            $this->errorMessage = 'This transaction reference has already been submitted.';

            return;
        }

        // Use remaining due if partially paid, otherwise full grand total.
        $paidAlready = $orders->amountPaid($this->order);
        $remainingDue = max(0, (int) $this->order->grand_total - $paidAlready);
        $amount = $remainingDue > 0 ? $remainingDue : (int) $this->order->grand_total;

        try {
            $orders->recordPayment(
                $this->order,
                $method,
                $amount,
                OrderPaymentStatus::Pending,
                $reference,
            );
        } catch (UniqueConstraintViolationException) {
            $this->errorMessage = 'This transaction reference has already been submitted.';

            return;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->successMessage = 'Payment submitted — awaiting verification. You will be notified once the shop confirms your payment.';
        $this->transaction_reference = '';
        $this->order->refresh();
        $this->order->load(['paymentMethod', 'payments']);
    }

    public function render()
    {
        $method = $this->order->paymentMethod;
        $requiresVerification = $method?->requires_verification ?? false;
        $isManual = $method && in_array($method->type->value, ['manual_mfs', 'bank_transfer'], true);

        $hasPending = $this->order->payments()->where('status', OrderPaymentStatus::Pending)->exists();
        $isPaid = $this->order->payments()->where('status', OrderPaymentStatus::Paid)->exists();

        return view('livewire.manual-payment-submission', [
            'paymentMethod' => $method,
            'requiresVerification' => $requiresVerification && $isManual,
            'hasPending' => $hasPending,
            'isPaid' => $isPaid,
        ]);
    }
}
