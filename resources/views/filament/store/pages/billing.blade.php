<x-filament-panels::page>
    @php($subscription = $this->getSubscription())
    @php($latestRequest = $this->getLatestPlanChangeRequest())

    <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-6 mb-6">
        <p class="font-semibold text-lg">Current Plan: {{ $subscription?->entitlement('plan_name') ?? 'None' }}</p>
        <p class="text-sm text-gray-500">Status: {{ $subscription?->status?->label() }}</p>
        @if ($subscription?->isTrialing())
            <p class="text-sm text-amber-600 mt-1">{{ $subscription->daysRemaining() }} day(s) left in your trial.</p>
        @endif
        @if ($latestRequest !== null)
            @if ($latestRequest->status === \App\Enums\PlanChangeRequestStatus::Pending)
                <p class="text-sm text-amber-600 mt-1">Plan change request pending: {{ $latestRequest->requestedPlan?->name }}</p>
            @else
                <p class="text-sm text-red-600 mt-1">Your request for {{ $latestRequest->requestedPlan?->name }} was declined.
                    @if ($latestRequest->rejection_reason)
                        Reason: {{ $latestRequest->rejection_reason }}.
                    @endif
                </p>
            @endif
        @endif
        <p class="text-sm mt-2">Products used: {{ $this->getProductUsage() }} /
            {{ $subscription?->entitlement('max_products') ?? '∞' }}</p>
        <p class="text-sm">Staff used: {{ $this->getStaffUsage() }} / {{ $subscription?->entitlement('max_staff') ?? '∞' }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach ($this->getPlans() as $plan)
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                <p class="font-semibold">{{ $plan->name }}</p>
                <p class="text-2xl font-bold my-2">৳{{ number_format($plan->price / 100) }}<span
                        class="text-sm font-normal">/{{ $plan->billing_period }}</span></p>
                <p class="text-sm text-gray-500">{{ $plan->max_products ?? 'Unlimited' }} products</p>
                <p class="text-sm text-gray-500">{{ $plan->max_staff ?? 'Unlimited' }} staff</p>
                @if ($subscription?->plan_id !== $plan->id)
                    <button wire:click="requestPlan({{ $plan->id }})"
                        class="mt-4 w-full py-2 rounded-lg bg-[var(--brand)] text-white text-sm">
                        Request This Plan
                    </button>
                @else
                    <span class="mt-4 block text-center text-sm text-green-600">Current Plan</span>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
