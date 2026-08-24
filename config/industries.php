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
        ],

        'sports' => [
            'label' => 'Sports',
            'card' => [
                'hover_gallery_recommended' => false,
            ],
            'theme' => [
                'preset' => 'sports',
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
        ],
    ],
];
