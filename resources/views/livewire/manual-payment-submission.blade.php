{{-- resources/views/livewire/manual-payment-submission.blade.php --}}
<div class="mt-6 rounded-2xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/40 p-5 text-left">
    <h3 class="font-semibold text-amber-900 dark:text-amber-100 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Manual Payment — Awaiting Verification
    </h3>

    @if ($paymentMethod)
        <div class="mt-3 text-sm space-y-1 text-amber-800 dark:text-amber-200">
            <p><span class="text-amber-700 dark:text-amber-300">Method:</span> {{ $paymentMethod->displayName() }}</p>
            @if ($paymentMethod->account_number)
                <p><span class="text-amber-700 dark:text-amber-300">Pay to:</span> <span class="font-mono font-medium">{{ $paymentMethod->account_number }}</span> @if($paymentMethod->account_name) — {{ $paymentMethod->account_name }} @endif</p>
            @endif
            @if ($paymentMethod->bank_name)
                <p><span class="text-amber-700 dark:text-amber-300">Bank:</span> {{ $paymentMethod->bank_name }} @if($paymentMethod->branch_name) ({{ $paymentMethod->branch_name }}) @endif</p>
            @endif
            @if ($paymentMethod->instructions)
                <p class="whitespace-pre-line pt-2 text-amber-900 dark:text-amber-100">{{ $paymentMethod->instructions }}</p>
            @endif
            <p class="text-xs pt-1">Amount: ৳{{ number_format($order->grand_total / 100, 2) }} · Reference: use your Order Number <strong>{{ $order->order_number }}</strong></p>
        </div>
    @endif

    @if ($isPaid)
        <div class="mt-4 rounded-xl bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-900 p-3 text-sm text-green-800 dark:text-green-200">
            Payment verified — thank you! Your order is being processed.
        </div>
    @elseif($hasPending)
        <div class="mt-4 rounded-xl bg-amber-100 dark:bg-amber-900/40 border border-amber-200 dark:border-amber-800 p-3 text-sm text-amber-800 dark:text-amber-200">
            We received your transaction reference. The shop will verify it shortly.
        </div>
    @endif

    @if ($successMessage)
        <div class="mt-4 rounded-xl bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-900 p-3 text-sm text-green-800 dark:text-green-200">
            {{ $successMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="mt-4 rounded-xl bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-900 p-3 text-sm text-red-800 dark:text-red-200">
            {{ $errorMessage }}
        </div>
    @endif

    @unless($isPaid)
        <form wire:submit="submit" class="mt-4 flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <x-ui.input name="transaction_reference" wire:model="transaction_reference" label="Transaction / Reference ID" placeholder="e.g. TrxID 9AX..." :error="$errors->first('transaction_reference')" />
            </div>
            <div class="sm:pt-6">
                <x-ui.button type="submit" variant="primary" loading-target="submit">I've Paid — Submit</x-ui.button>
            </div>
        </form>
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">Enter the Transaction ID you received from bKash/Nagad/Rocket or bank after sending the payment.</p>
    @endunless
</div>