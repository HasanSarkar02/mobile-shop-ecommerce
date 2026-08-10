@props(['rating' => 0, 'count' => null])
<div class="flex items-center gap-1.5">
    <div class="flex text-amber-500 leading-none" aria-hidden="true">
        @for ($i = 1; $i <= 5; $i++)
            <span>{{ $i <= round($rating) ? '★' : '☆' }}</span>
        @endfor
    </div>
    @if ($count !== null)
        <span class="text-sm text-gray-500">({{ $count }})</span>
    @endif
</div>
