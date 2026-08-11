@extends('storefront.layout')

@section('title', tenant()->name)

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-10 sm:pt-6 sm:pb-14 space-y-14 sm:space-y-16">
        @foreach ($sections as $section)
            <div class="{{ [
                'all' => '',
                'desktop' => 'hidden md:block',
                'mobile' => 'md:hidden',
            ][$section->visibility->value] }}">
            @switch($section->type->value)
                @case('banner_carousel')
                    @include('storefront.partials.sections.banner-carousel', ['section' => $section])
                @break

                @case('product_grid')
                    @include('storefront.partials.sections.product-grid', ['section' => $section])
                @break

                @case('category_grid')
                    @include('storefront.partials.sections.category-grid', ['section' => $section])
                @break

                @case('trust_badges')
                    @include('storefront.partials.sections.trust-badges', ['section' => $section])
                @break

                @case('newsletter_cta')
                    @include('storefront.partials.sections.newsletter-cta', ['section' => $section])
                @break

                @case('custom_html')
                    @include('storefront.partials.sections.custom-html', ['section' => $section])
                @break
            @endswitch
            </div>
        @endforeach
    </div>
@endsection
