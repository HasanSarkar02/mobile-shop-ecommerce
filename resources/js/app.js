// resources/js/app.js
//
// Livewire v4 bundles its own Alpine instance internally. Importing Alpine
// separately from the 'alpinejs' package (as this file used to) creates a
// SECOND, independent Alpine instance alongside Livewire's — plugins/state
// registered on one are invisible to the other, and Livewire's own
// x-data-driven internals stop working correctly.
//
// The correct pattern (per Livewire's own docs, "manually bundling Alpine"):
// import Alpine from Livewire's ESM build, register plugins on that same
// instance, then let Livewire.start() start Alpine too. Never call
// Alpine.start() yourself here — see layout.blade.php, which uses
// @livewireScriptConfig (not @livewireScripts) to suppress Livewire's own
// auto-injected script tag in favor of this bundle.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

// Global UI state that needs to be triggered from more than one place in the
// DOM tree (the mobile header's hamburger button, and the "Categories" tab
// in the mobile bottom nav both open the same drawer, but sit in separate
// x-data scopes as siblings in layout.blade.php — a plain local x-data
// variable can't coordinate across them). Registered before Livewire.start()
// so it exists for the very first paint.
document.addEventListener('alpine:init', () => {
    Alpine.store('ui', {
        mobileMenuOpen: false,
    });
});

Livewire.start();