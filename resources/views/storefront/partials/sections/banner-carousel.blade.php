@php
    $banners = \App\Models\Banner::query()
        ->where('placement', $section->config['placement'] ?? 'hero')
        ->when($section->campaign_id, fn($q) => $q->where('campaign_id', $section->campaign_id))
        ->currentlyActive()
        ->orderBy('sort_order')
        ->get();
@endphp
@if ($banners->isNotEmpty())
    <div x-data="{ active: 0 }" x-init="setInterval(() => active = (active + 1) % {{ $banners->count() }}, 5000)" class="relative rounded-2xl overflow-hidden">
        @foreach ($banners as $i => $banner)
            <a href="{{ $banner->resolveUrl() ?? '#' }}" x-show="active === {{ $i }}">
                @if ($banner->media_type->value === 'video')
                    <video src="{{ $banner->getFirstMediaUrl('video') }}" autoplay muted loop playsinline
                        class="w-full aspect-[21/9] object-cover"></video>
                @else
                    <img src="{{ $banner->getFirstMediaUrl('image', 'large') }}"
                        class="w-full aspect-[21/9] object-cover">
                @endif
            </a>
        @endforeach
        @if ($banners->count() > 1)
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                @foreach ($banners as $i => $banner)
                    <button @click="active = {{ $i }}" class="w-2 h-2 rounded-full bg-white/70"></button>
                @endforeach
            </div>
        @endif
    </div>
@endif
