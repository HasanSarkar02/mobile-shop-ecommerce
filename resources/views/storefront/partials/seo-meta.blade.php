@if ($description ?? null)
    @push('meta')
        <meta name="description" content="{{ $description }}">
    @endpush
@endif
@push('meta')
    <link rel="canonical" href="{{ $canonical ?? app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalPath(tenant(), request()->getPathInfo()) }}">
@endpush
@if ($robots ?? null)
    @push('meta')
        <meta name="robots" content="{{ $robots }}">
    @endpush
@endif
