<?php

declare(strict_types=1);

use App\Support\IndustryConfig;

it('resolves every registered preset', function (): void {
    $codes = IndustryConfig::codes();

    expect($codes)->toHaveCount(7);

    foreach (['general', 'mobile', 'electronics', 'fashion', 'grocery', 'sports', 'furniture'] as $code) {
        expect($codes)->toContain($code);
    }
});

it('merges preset deltas over the general baseline', function (): void {
    // fashion only declares a few keys — the rest must come from general.
    $preset = IndustryConfig::resolve('fashion');

    expect($preset['pdp']['layout'])->toBe('imagery-led')
        ->and($preset['card']['hover_gallery_enabled'])->toBeTrue()
        ->and($preset['card']['hover_gallery_recommended'])->toBeTrue()
        ->and($preset['facets']['priority'])->toBe([]);
});

it('falls back to general for unknown and null industries', function (): void {
    foreach ([null, '', 'spaceships'] as $industry) {
        $preset = IndustryConfig::resolve($industry);

        expect($preset)->toBe(IndustryConfig::resolve('general'))
            ->and($preset['label'])->toBe('General');
    }
});

it('reads dot-notation values with the fallback applied', function (): void {
    expect(IndustryConfig::get(null, 'pdp.layout'))->toBe('standard')
        ->and(IndustryConfig::get('mobile', 'theme.preset'))->toBe('electronics')
        ->and(IndustryConfig::get('grocery', 'card.hover_gallery_enabled'))->toBeFalse()
        ->and(IndustryConfig::get(null, 'missing.key', 'fallback'))->toBe('fallback');
});
