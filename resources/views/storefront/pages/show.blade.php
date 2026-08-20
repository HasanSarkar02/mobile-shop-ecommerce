@extends('storefront.layout')

@section('title', ($page->meta_title ?: $page->title) . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'description' => $page->meta_description,
        'canonical' => app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.page', [$page->slug]),
    ])

    <div class="max-w-3xl mx-auto px-4 py-12 prose dark:prose-invert">
        <h1>{{ $page->title }}</h1>
        {!! $page->sanitizedContent() !!}
    </div>
@endsection
