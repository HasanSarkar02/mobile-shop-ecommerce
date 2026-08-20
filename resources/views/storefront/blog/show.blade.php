@extends('storefront.layout')

@section('title', ($post->meta_title ?: $post->title) . ' - ' . tenant()->name)

@section('content')
    @php
        $canonicalBlogUrl = app(\App\Support\Tenancy\TenantUrlGenerator::class)
            ->canonicalRoute(tenant(), 'storefront.blog.show', [$post->slug]);
    @endphp
    @include('storefront.partials.seo-meta', [
        'description' => $post->meta_description ?: $post->excerpt,
        'canonical' => $canonicalBlogUrl,
    ])

    <div class="max-w-3xl mx-auto px-4 py-8 prose dark:prose-invert">
        <h1>{{ $post->title }}</h1>
        @if ($url = $post->getFirstMediaUrl('cover', 'large'))
            <img src="{{ $url }}" class="rounded-lg">
        @endif
        {!! $post->sanitizedContent() !!}
    </div>

    @push('meta')
        <script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'url' => $canonicalBlogUrl,
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalBlogUrl],
    'headline' => $post->title,
    'datePublished' => $post->published_at?->toIso8601String(),
    'author' => ['@type' => 'Organization', 'name' => tenant()->name],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
    @endpush
@endsection
