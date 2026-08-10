@extends('platform.layout')

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-24 text-center">
        <h1 class="text-4xl font-bold mb-4">Launch your mobile shop online, in minutes.</h1>
        <p class="text-lg text-gray-500 mb-8">Catalog, orders, payments, and inventory — everything you need to sell phones
            and accessories online in Bangladesh.</p>
        <x-ui.button as="a" href="{{ route('platform.signup') }}"
            onclick="window.location='{{ route('platform.signup') }}'" size="lg">
            Start Free Trial
        </x-ui.button>
    </div>
@endsection
