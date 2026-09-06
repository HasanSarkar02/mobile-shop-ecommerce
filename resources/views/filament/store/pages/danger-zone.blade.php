<x-filament-panels::page>
    @php
        $tenant = tenant();
        $isPending = $tenant?->isPendingDeletion();
        $scheduled = $tenant?->deletion_scheduled_at;
    @endphp

    <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/20 p-6 mb-6">
        <p class="font-semibold text-amber-900 dark:text-amber-100">Danger Zone — Delete Store</p>
        <p class="text-sm text-amber-800 dark:text-amber-200 mt-1">
            Deleting your store will schedule it for deletion with a 7-day grace period. During grace you can cancel.
            After 7 days it will be soft-deleted, subdomain mutated to <code>deleted-*</code> and tombstoned:
            trial with 0 orders → 0-day quarantine (immediate reuse), active/used → 30-day quarantine, abuse → permanent.
            Ordinary data is purged asynchronously; tombstone survives. Financial records are retained minimally per law.
        </p>
    </div>

    @if ($isPending)
        <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/20 p-6 mb-6">
            <p class="font-semibold text-red-800 dark:text-red-100">Deletion scheduled</p>
            <p class="text-sm text-red-700 dark:text-red-200 mt-1">
                Scheduled for: <strong>{{ $scheduled?->toDateTimeString() }}</strong> ({{ $scheduled?->diffForHumans() }})<br>
                Reason: {{ $tenant->deletion_reason ?? '—' }}
            </p>
            <p class="text-xs text-red-600 dark:text-red-300 mt-2">Your store is still accessible until the scheduled time. Billing for renewal has been stopped (period-end).</p>
            <x-filament::button wire:click="cancelDeletion" color="gray" class="mt-4">Cancel Deletion</x-filament::button>
        </div>
    @else
        <form wire:submit="requestDeletion" class="space-y-4 max-w-2xl">
            <div>
                <label class="block text-sm font-medium mb-1">Reason for deletion *</label>
                <textarea wire:model="reason" rows="3" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 p-3 text-sm" placeholder="e.g. No longer needed, moving to other platform"></textarea>
                @error('reason') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 text-sm text-gray-600 dark:text-gray-300">
                <p>By confirming, you agree:</p>
                <ul class="list-disc list-inside mt-1 space-y-1">
                    <li>7-day grace, then soft-delete + subdomain mutated + tombstone.</li>
                    <li>Billing stops at period end; no new charges.</li>
                    <li>After quarantine, subdomain may be reused by others (except permanent abuse).</li>
                </ul>
            </div>
            <x-filament::button type="submit" color="danger" icon="heroicon-o-trash">
                Request Deletion — Type DELETE to confirm
            </x-filament::button>
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.querySelector('form[wire\\:submit="requestDeletion"]');
                if (!form) return;
                form.addEventListener('submit', (e) => {
                    if (!confirm('Type DELETE to confirm. This will schedule deletion in 7 days.')) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                    }
                });
            });
        </script>
    @endif

    <div class="mt-8 text-xs text-gray-500">
        <p>Platform admin can hard-purge after soft-delete (async queued job). Owner cannot hard-delete.</p>
        <p>Inactivity: 60d no login/orders → auto-suspend (no auto-delete).</p>
    </div>
</x-filament-panels::page>
