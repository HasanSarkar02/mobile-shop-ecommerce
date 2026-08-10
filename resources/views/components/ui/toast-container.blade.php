<div x-data="{
    toasts: [],
    add(message, type = 'success') {
        const id = Date.now() + Math.random();
        this.toasts.push({ id, message, type });
        setTimeout(() => this.remove(id), 4000);
    },
    remove(id) {
        this.toasts = this.toasts.filter(t => t.id !== id);
    },
}" @toast.window="add($event.detail.message, $event.detail.type)" x-init="@if(session('status'))
add(@js(session('status')), 'success');
@endif
@if(session('error'))
add(@js(session('error')), 'error');
@endif"
    class="fixed bottom-4 right-4 z-[100] space-y-2 w-80" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="true" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0"
            class="rounded-xl shadow-lg px-4 py-3 text-sm text-white"
            :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900 dark:bg-gray-100 dark:text-gray-900'">
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
