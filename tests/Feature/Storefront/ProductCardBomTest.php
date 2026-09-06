<?php

declare(strict_types=1);

it('product-card Blade files contain no UTF-8 BOM', function (): void {
    $files = [
        resource_path('views/components/storefront/product-cards/electronics.blade.php'),
        resource_path('views/components/storefront/product-cards/default.blade.php'),
        resource_path('views/components/storefront/product-cards/fashion.blade.php'),
        resource_path('views/components/storefront/product-cards/grocery.blade.php'),
    ];

    foreach ($files as $file) {
        expect(file_exists($file))->toBeTrue("Missing file: {$file}");

        $contents = file_get_contents($file);
        // BOM is EF BB BF at byte 0, also check anywhere
        expect(substr($contents, 0, 3))->not->toBe("\xEF\xBB\xBF", "BOM at start of {$file}");
        expect(str_contains($contents, "\xEF\xBB\xBF"))->toBeFalse("BOM bytes found inside {$file}");
        expect(str_contains($contents, "\u{FEFF}"))->toBeFalse("FEFF character found in {$file}");
    }
});
