<?php

declare(strict_types=1);

namespace App\Support;

final class ColorHelper
{
    /**
     * Resolve a color name to a hex code for swatch display.
     * Used by fashion/electronics cards and PDP swatches.
     * Falls back to deterministic HSL when not in map.
     */
    public static function resolveColorHex(string $name): string
    {
        $map = [
            'black' => '#111827',
            'white' => '#ffffff',
            'lavender' => '#C8A2C8',
            'beige' => '#D6CBB8',
            'pink' => '#F4A9B8',
            'mint' => '#A8E6CF',
            'teal' => '#0d9488',
            'navy' => '#1e3a5f',
            'blue' => '#2563eb',
            'red' => '#dc2626',
            'maroon' => '#7f1d1d',
            'burgundy' => '#800020',
            'gray' => '#9ca3af',
            'grey' => '#9ca3af',
            'mustard' => '#eab308',
            'yellow' => '#facc15',
            'olive' => '#84a98c',
            'sage' => '#9CAFAA',
            'cream' => '#FFFDD0',
            'brown' => '#92400e',
            'tan' => '#D2B48C',
            'peach' => '#FFCBA4',
            'coral' => '#FF7F50',
            'orange' => '#f97316',
            'purple' => '#7c3aed',
            'lilac' => '#C8A2C8',
            'ivory' => '#FFFFF0',
            'charcoal' => '#374151',
            'khaki' => '#C3B091',
            'denim' => '#1560BD',
            'indigo' => '#4B0082',
            'emerald' => '#059669',
            'forest' => '#228B22',
            'sky' => '#87CEEB',
            'aqua' => '#00FFFF',
            'turquoise' => '#40E0D0',
            'gold' => '#D4AF37',
            'silver' => '#C0C0C0',
            'bronze' => '#CD7F32',
            'blush' => '#F8BBD0',
            'mauve' => '#E0B0FF',
            'plum' => '#8E4585',
            'rust' => '#B7410E',
            'camel' => '#C19A6B',
            'sand' => '#C2B280',
            'stone' => '#928E85',
            'ash' => '#B2BEB5',
            'slate' => '#708090',
            'wine' => '#722F37',
            'chocolate' => '#7B3F00',
            'deep blue' => '#1E3A8A',
            'cosmic orange' => '#FF6B35',
            'white titanium' => '#F5F5F0',
        ];

        $key = strtolower(trim($name));
        if (isset($map[$key])) {
            return $map[$key];
        }

        $hash = crc32($key);
        $hue = abs((int) $hash) % 360;

        return "hsl({$hue} 55% 65%)";
    }
}
