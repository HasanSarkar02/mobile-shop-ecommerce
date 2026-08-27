<?php

/**
 * Industry composition/config presets (Phase F.5).
 *
 * Governing rule: everything vertical-specific is data/configuration — never
 * duplicated application code or per-vertical page implementations. These
 * presets declare how shared UI primitives should be *composed* per industry;
 * they are consumed by resolvers/primitives, never rendered directly.
 *
 * Each preset only declares deltas from `presets.general` — the resolver
 * merges them over the general baseline, so a missing key always yields the
 * safe default.
 *
 * Keys:
 * - label                     Display name (admin UI consumes this later).
 * - card.hover_gallery_enabled     Desktop hover-preview gallery switch. NEVER
 *                                  auto-enabled anywhere (F.6 locked decision);
 *                                  stays false in every preset until a
 *                                  storefront explicitly opts in.
 * - card.hover_gallery_recommended The per-industry usefulness verdict from
 *                                  the F.6 audit (fashion strongly useful ·
 *                                  electronics/mobile useful · furniture
 *                                  useful · sports optional · grocery usually
 *                                  unnecessary). Advisory only.
 * - pdp.layout                standard | spec-led | imagery-led.
 * - pdp.information_priority  Section ordering hint for the PDP nav.
 * - facets.priority           Attribute codes to surface first. Empty by
 *                             design: attribute codes are tenant-defined EAV
 *                             data, so nothing is hardcoded here yet (grocery
 *                             unit facets land with Phase C's UOM entity).
 * - theme.preset              Design-token preset name (Phase B consumes;
 *                             no CSS changes exist until then).
 * - ui.container_class        Full Tailwind container classes for the main
 *                             storefront wrapper (e.g. 'max-w-[1600px] mx-auto').
 *                             Must be a literal string for Tailwind JIT purge.
 * - ui.grid_class             Full Tailwind grid classes for product grids
 *                             (e.g. 'grid-cols-2 md:grid-cols-3 lg:grid-cols-5').
 * - ui.image_aspect           Tailwind aspect ratio for card images.
 * - ui.card_component         Blade component path for the product card.
 */
return [

    'default' => 'general',

    'presets' => [

        'general' => [
            'label' => 'General',
            'card' => [
                'hover_gallery_enabled' => false,
                'hover_gallery_recommended' => false,
            ],
            'pdp' => [
                'layout' => 'standard',
                'information_priority' => ['specifications', 'description', 'reviews'],
            ],
            'facets' => [
                'priority' => [],
            ],
            'theme' => [
                'preset' => 'brand',
            ],
            'ui' => [
                'container_class' => 'max-w-7xl mx-auto',
                'grid_class' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
                'image_aspect' => 'aspect-square',
                'card_component' => 'storefront.product-cards.default',
            ],
        ],

        'mobile' => [
            'label' => 'Mobile',
            'card' => [
                'hover_gallery_recommended' => true,
            ],
            'pdp' => [
                'layout' => 'spec-led',
            ],
            'theme' => [
                'preset' => 'electronics',
            ],
            'ui' => [
                'container_class' => 'max-w-[1440px] mx-auto',
                'grid_class' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
                'image_aspect' => 'aspect-square',
                'card_component' => 'storefront.product-cards.electronics',
            ],
        ],

        'electronics' => [
            'label' => 'Electronics',
            'card' => [
                'hover_gallery_recommended' => true,
            ],
            'pdp' => [
                'layout' => 'spec-led',
            ],
            'theme' => [
                'preset' => 'electronics',
            ],
            'ui' => [
                'container_class' => 'max-w-[1440px] mx-auto',
                'grid_class' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
                'image_aspect' => 'aspect-square',
                'card_component' => 'storefront.product-cards.electronics',
            ],
        ],

        'fashion' => [
            'label' => 'Fashion',
            'card' => [
                'hover_gallery_recommended' => true,
            ],
            'pdp' => [
                'layout' => 'imagery-led',
                'information_priority' => ['description', 'specifications', 'reviews'],
            ],
            'theme' => [
                'preset' => 'fashion',
            ],
            'ui' => [
                'container_class' => 'max-w-[1440px] mx-auto',
                'grid_class' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
                'image_aspect' => 'aspect-[3/4]',
                'card_component' => 'storefront.product-cards.fashion',
            ],
        ],

        'grocery' => [
            'label' => 'Grocery',
            'card' => [
                'hover_gallery_recommended' => false,
            ],
            'pdp' => [
                'layout' => 'standard',
                'information_priority' => ['description', 'reviews'],
            ],
            'theme' => [
                'preset' => 'grocery',
            ],
            'ui' => [
                'container_class' => 'max-w-[1600px] mx-auto',
                'grid_class' => 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6',
                'image_aspect' => 'aspect-square',
                'card_component' => 'storefront.product-cards.grocery',
            ],
        ],

        'sports' => [
            'label' => 'Sports',
            'card' => [
                'hover_gallery_recommended' => false,
            ],
            'theme' => [
                'preset' => 'sports',
            ],
            'ui' => [
                'container_class' => 'max-w-7xl mx-auto',
                'grid_class' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
                'image_aspect' => 'aspect-square',
                'card_component' => 'storefront.product-cards.default',
            ],
        ],

        'furniture' => [
            'label' => 'Furniture',
            'card' => [
                'hover_gallery_recommended' => true,
            ],
            'pdp' => [
                'layout' => 'imagery-led',
            ],
            'theme' => [
                'preset' => 'furniture',
            ],
            'ui' => [
                'container_class' => 'max-w-7xl mx-auto',
                'grid_class' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
                'image_aspect' => 'aspect-square',
                'card_component' => 'storefront.product-cards.default',
            ],
        ],
    ],
];
