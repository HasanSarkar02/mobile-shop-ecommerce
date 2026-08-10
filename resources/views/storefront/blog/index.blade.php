@extends('storefront.layout')

@section('title', 'Blog - ' . tenant()->name)

@section('content')
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Blog</h1>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach ($posts as $post)
                <a href="{{ route('storefront.blog.show', $post->slug) }}" class="block">
                    @if ($url = $post->getFirstMediaUrl('cover', 'large'))
                        <img src="{{ $url }}" class="w-full aspect-video object-cover rounded-lg mb-3">
                    @endif
                    <p class="font-medium">{{ $post->title }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $post->excerpt }}</p>
                </a>
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    </div>
@endsection
