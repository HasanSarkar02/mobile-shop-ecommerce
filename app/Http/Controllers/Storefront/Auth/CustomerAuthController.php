<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CustomerAuthController extends Controller
{
    public function showLogin()
    {
        return view('storefront.auth.login');
    }

    public function login(Request $request, CartService $carts, WishlistService $wishlists): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('customer')->attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        $customer = Auth::guard('customer')->user();
        $customer->update(['last_login_at' => now()]);

        if ($cartToken = $request->cookie('cart_token')) {
            $carts->mergeGuestCartIntoCustomer($cartToken, $customer);
        }

        if ($wishlistToken = $request->cookie('wishlist_token')) {
            $wishlists->mergeGuestIntoCustomer($wishlistToken, $customer);
        }

        return redirect()->route('storefront.account.dashboard');
    }

    public function showRegister()
    {
        return view('storefront.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => tenant()->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        Auth::guard('customer')->login($customer);

        return redirect()->route('storefront.account.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();

        return redirect()->route('storefront.home');
    }
}