<?php

declare(strict_types=1);

use App\Support\IndustryConfig;

it('every preset exposes trust_badges with 4 items', function (): void {
    foreach (['general', 'mobile', 'electronics', 'fashion', 'grocery', 'sports', 'furniture'] as $code) {
        $items = IndustryConfig::get($code, 'trust_badges.items');

        expect($items)->toBeArray()->toHaveCount(4);

        foreach ($items as $item) {
            expect($item)->toHaveKeys(['icon', 'label', 'sub'])
                ->and($item['icon'])->toBeString()->not->toBeEmpty()
                ->and($item['label'])->toBeString()->not->toBeEmpty()
                ->and($item['sub'])->toBeString()->not->toBeEmpty();
        }
    }
});

it('grocery gets grocery-specific content not general leakage', function (): void {
    $grocery = IndustryConfig::get('grocery', 'trust_badges.items');
    $general = IndustryConfig::get('general', 'trust_badges.items');

    expect($grocery[0]['label'])->toBe('Fresh & Quality')
        ->and($grocery[1]['label'])->toBe('Same-Day Delivery')
        ->and($grocery)->not->toEqual($general);
});

it('furniture gets furniture-specific content', function (): void {
    $items = IndustryConfig::get('furniture', 'trust_badges.items');

    expect($items[0]['label'])->toBe('Quality Assured')
        ->and($items[2]['label'])->toBe('Installation Available');
});

it('whole-array replacement prevents trailing general leakage', function (): void {
    // grocery 4 items must exactly equal its preset, not general tail
    $grocery = IndustryConfig::get('grocery', 'trust_badges.items');
    expect($grocery)->toHaveCount(4);
    // If array_replace_recursive leaked, grocery would still contain general tail like "Genuine Products"
    $labels = array_column($grocery, 'label');
    expect($labels)->not->toContain('Genuine Products');
});

it('pdp.information_priority remains whole-array replaced per preset', function (): void {
    expect(IndustryConfig::get('fashion', 'pdp.information_priority'))->toBe(['description', 'specifications', 'warranty', 'reviews', 'faq'])
        ->and(IndustryConfig::get('general', 'pdp.information_priority'))->toBe(['specifications', 'description', 'warranty', 'reviews', 'faq']);
});

it('facets.priority remains empty and unknown industry falls back to general', function (): void {
    expect(IndustryConfig::get('electronics', 'facets.priority'))->toBe([])
        ->and(IndustryConfig::get('unknown_vertical', 'trust_badges.items'))->toBe(IndustryConfig::get('general', 'trust_badges.items'))
        ->and(IndustryConfig::resolve(null)['label'])->toBe('General')
        ->and(IndustryConfig::resolve('spaceships')['label'])->toBe('General');
});
