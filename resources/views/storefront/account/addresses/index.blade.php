@extends('storefront.account.layout')

@section('title', 'My Addresses - ' . tenant()->name)

@section('account-content')
    <h1 class="text-xl font-bold mb-4">My Addresses</h1>
    @foreach ($addresses as $address)
        <div class="py-3 border-b border-gray-100 dark:border-gray-800 flex justify-between">
            <span>{{ $address->recipient_name }}, {{ $address->address_line_1 }}, {{ $address->city }}</span>
            <form method="POST" action="{{ route('storefront.account.addresses.destroy', $address) }}">@csrf
                @method('DELETE')<button class="text-red-500 text-sm">Delete</button></form>
        </div>
    @endforeach

    <form method="POST" action="{{ route('storefront.account.addresses.store') }}" class="mt-6 grid grid-cols-2 gap-3">
        @csrf
        <input type="hidden" name="type" value="shipping">
        <input name="recipient_name" placeholder="Recipient name"
            class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
        <input name="phone" placeholder="Phone" class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700"
            required>
        <input name="address_line_1" placeholder="Address"
            class="col-span-2 rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
        <input name="city" placeholder="City" class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700"
            required>
        <button class="col-span-2 py-2 rounded-lg bg-[var(--brand)] text-white">Add Address</button>
    </form>
@endsection
