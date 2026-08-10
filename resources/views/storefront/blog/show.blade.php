@extends('storefront.layout')

@section('title', ($post->meta_title ?: $post->title) . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'description' => $post->meta_description ?: $post->excerpt,
        'canonical' => route('storefront.blog.show', $post->slug),
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
    'headline' => $post->title,
    'datePublished' => $post->published_at?->toIso8601String(),
    'author' => ['@type' => 'Organization', 'name' => tenant()->name],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
    @endpush
@endsection
