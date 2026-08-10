@extends('storefront.layout')

@section('title', 'Frequently Asked Questions - ' . tenant()->name)

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Frequently Asked Questions</h1>
        @foreach ($faqs as $faq)
            <div x-data="{ expanded: false }" class="border-b border-gray-100 dark:border-gray-800 py-4">
                <button @click="expanded = !expanded" class="w-full text-left font-medium">{{ $faq->question }}</button>
                <div x-show="expanded" class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $faq->answer }}</div>
            </div>
        @endforeach
    </div>

    @push('meta')
        <script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => $f->question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f->answer)]])->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
    @endpush
@endsection
