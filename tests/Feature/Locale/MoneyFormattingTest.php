<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;

it('formats money with trailing zeros for PDP/Checkout/EMI (en)', function (): void {
    App::setLocale('en');
    expect(money(120000))->toBe('৳1,200.00');
    expect(money(120050))->toBe('৳1,200.50');
    expect(money(0))->toBe('৳0.00');
});

it('formats money without trailing zeros for cards (en)', function (): void {
    App::setLocale('en');
    expect(money_without_trailing_zeros(120000))->toBe('৳1,200');
    expect(money_without_trailing_zeros(120050))->toBe('৳1,200');
    expect(money_without_trailing_zeros(0))->toBe('৳0');
});

it('keeps Western numerals even when locale is bn', function (): void {
    App::setLocale('bn');
    expect(money(120000))->toBe('৳1,200.00');
    expect(money_without_trailing_zeros(120000))->toBe('৳1,200');
    // Ensure no Bengali digits appear
    expect(money(123456))->not->toContain('১');
    expect(money(123456))->not->toContain('২');
});

it('respects currency symbol and explicit locale param', function (): void {
    App::setLocale('en');
    expect(money(10000, 'USD'))->toBe('$100.00');
    expect(money(10000, 'BDT', 'bn'))->toBe('৳100.00');
    expect(money(10000, 'USD', 'bn'))->toBe('$100.00');
});

it('renders price component without trailing zeros', function (): void {
    App::setLocale('en');
    $html = view('components.ui.price', ['price' => 120000, 'compareAtPrice' => 150000])->render();
    expect($html)->toContain('৳1,200');
    expect($html)->not->toContain('৳1,200.00');
    expect($html)->toContain('৳1,500');
});

it('renders EMI server fallback via money helper', function (): void {
    App::setLocale('en');
    // Simulate EMI fallback rendering via show.blade logic
    $emi = (int) round((100000 * (1 + 0 / 100)) / 12); // 8333
    expect(money($emi))->toBe('৳83.33');
    expect(money($emi * 12))->toBe('৳999.96');
});
