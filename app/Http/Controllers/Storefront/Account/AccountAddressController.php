<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountAddressController extends Controller
{
    public function index()
    {
        $addresses = Address::query()->where('customer_id', Auth::guard('customer')->id())->get();

        return view('storefront.account.addresses.index', compact('addresses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['customer_id'] = Auth::guard('customer')->id();
        $data['tenant_id'] = tenant()->id;

        Address::query()->create($data);

        return back()->with('status', 'Address added.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->customer_id === Auth::guard('customer')->id(), 404);

        $address->update($this->validated($request));

        return back()->with('status', 'Address updated.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        abort_unless($address->customer_id === Auth::guard('customer')->id(), 404);

        $address->delete();

        return back()->with('status', 'Address removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:shipping,billing'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
        ]);
    }
}
