@extends('storefront.layout')

@section('title', tenant()->name)

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-16">
        @foreach ($sections as $section)
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

                @case('custom_html')
                    @include('storefront.partials.sections.custom-html', ['section' => $section])
                @break
            @endswitch
        @endforeach
    </div>
@endsection
