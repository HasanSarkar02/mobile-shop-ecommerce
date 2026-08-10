@if ($description ?? null)
    @push('meta')
        <meta name="description" content="{{ $description }}">
    @endpush
@endif
@push('meta')
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
@endpush
@if ($robots ?? null)
    @push('meta')
        <meta name="robots" content="{{ $robots }}">
    @endpush
@endif
