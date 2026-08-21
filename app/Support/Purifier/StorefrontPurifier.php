<?php

namespace App\Support\Purifier;

use HTMLPurifier_AttrDef_CSS_AlphaValue;
use HTMLPurifier_AttrDef_CSS_Composite;
use HTMLPurifier_AttrDef_CSS_Length;
use HTMLPurifier_AttrDef_CSS_Multiple;
use HTMLPurifier_AttrDef_CSS_Number;
use HTMLPurifier_AttrDef_CSS_Percentage;
use HTMLPurifier_AttrDef_Enum;
use HTMLPurifier_AttrDef_Integer;
use Mews\Purifier\Facades\Purifier;

/**
 * Purifies storefront custom HTML while keeping modern CSS properties
 * (flex, grid, gap, border-radius, box-shadow, transform, ...) intact.
 *
 * HTMLPurifier only knows the properties in HTMLPurifier_CSSDefinition and
 * CSS.AllowedProperties cannot whitelist unknown ones, so modern properties
 * are registered on the raw CSS definition before the config is finalized.
 * The storefront definition cache is disabled and the definition is rebuilt
 * on every request, so registration always runs.
 */
class StorefrontPurifier
{
    /**
     * @param  string  $profile  Purifier profile name, defaults to 'storefront'.
     */
    public static function clean(?string $html, string $profile = 'storefront'): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return (string) Purifier::clean($html, $profile, function ($config) {
            StorefrontPurifier::configure($config);
        });
    }

    /**
     * Registers modern CSS properties on the raw CSS definition.
     *
     * Called from the mews config hook. The closure is rebound to the mews
     * Purifier instance, so the class is referenced by its fully qualified
     * name.
     *
     * @param  \HTMLPurifier_Config  $config
     */
    public static function configure($config): void
    {
        $css = self::getRawCssDefinition($config);

        if ($css !== null) {
            self::registerModernCssProperties($css);
        }
    }

    /**
     * Retrieves the not-yet-set-up CSS definition so modern properties can
     * be registered before HTMLPurifier finalizes it.
     *
     * HTMLPurifier has no CSS.DefinitionID directive, so the non-optimized
     * raw retrieval is used (definitions are rebuilt each request and never
     * written to the definition cache). The one benign warning it emits for
     * the missing CSS.DefinitionID lookup is suppressed.
     *
     * @param  \HTMLPurifier_Config  $config
     * @return \HTMLPurifier_CSSDefinition|null
     */
    private static function getRawCssDefinition($config)
    {
        set_error_handler(static function () {
            return true;
        });

        try {
            return $config->getCSSDefinition(true, false);
        } finally {
            restore_error_handler();
        }
    }

    private static function registerModernCssProperties($css): void
    {
        $enum = static function (array $values) {
            return new HTMLPurifier_AttrDef_Enum($values);
        };

        $css->info['display'] = $enum([
            'none', 'block', 'inline', 'inline-block', 'flex', 'inline-flex',
            'grid', 'inline-grid', 'contents', 'flow-root', 'list-item',
            'table', 'inline-table', 'table-row', 'table-row-group',
            'table-header-group', 'table-footer-group', 'table-column',
            'table-column-group', 'table-cell', 'table-caption',
            'inherit', 'initial', 'unset',
        ]);

        $css->info['visibility'] = $enum([
            'visible', 'hidden', 'collapse', 'inherit', 'initial', 'unset',
        ]);

        $css->info['overflow'] =
        $css->info['overflow-x'] =
        $css->info['overflow-y'] = $enum([
            'visible', 'hidden', 'scroll', 'auto', 'clip', 'inherit', 'initial', 'unset',
        ]);

        $css->info['opacity'] = new HTMLPurifier_AttrDef_CSS_AlphaValue;

        $css->info['position'] = $enum([
            'static', 'relative', 'absolute', 'fixed', 'sticky', 'inherit', 'initial', 'unset',
        ]);

        $inset = new HTMLPurifier_AttrDef_CSS_Composite([
            new HTMLPurifier_AttrDef_CSS_Length,
            new HTMLPurifier_AttrDef_CSS_Percentage,
            new HTMLPurifier_AttrDef_Enum(['auto']),
        ]);

        $css->info['top'] =
        $css->info['right'] =
        $css->info['bottom'] =
        $css->info['left'] = $inset;

        $css->info['z-index'] = new HTMLPurifier_AttrDef_CSS_Composite([
            new HTMLPurifier_AttrDef_Integer,
            new HTMLPurifier_AttrDef_Enum(['auto']),
        ]);

        $borderRadiusCorner = new HTMLPurifier_AttrDef_CSS_Composite([
            new HTMLPurifier_AttrDef_CSS_Percentage(true),
            new HTMLPurifier_AttrDef_CSS_Length('0'),
        ]);

        $css->info['border-top-left-radius'] =
        $css->info['border-top-right-radius'] =
        $css->info['border-bottom-left-radius'] =
        $css->info['border-bottom-right-radius'] = new HTMLPurifier_AttrDef_CSS_Multiple($borderRadiusCorner, 2);

        $css->info['border-radius'] = new HTMLPurifier_AttrDef_CSS_Multiple($borderRadiusCorner, 4);

        $css->info['flex-grow'] = $css->info['flex-shrink'] = new HTMLPurifier_AttrDef_CSS_Number(true);

        $css->info['flex-basis'] = new HTMLPurifier_AttrDef_CSS_Composite([
            new HTMLPurifier_AttrDef_CSS_Length,
            new HTMLPurifier_AttrDef_CSS_Percentage,
            new HTMLPurifier_AttrDef_Enum(['auto', 'content']),
        ]);

        $css->info['flex-direction'] = $enum([
            'row', 'row-reverse', 'column', 'column-reverse', 'inherit', 'initial', 'unset',
        ]);

        $css->info['flex-wrap'] = $enum([
            'nowrap', 'wrap', 'wrap-reverse', 'inherit', 'initial', 'unset',
        ]);

        $flexPart = new HTMLPurifier_AttrDef_CSS_Composite([
            new HTMLPurifier_AttrDef_CSS_Number(true),
            new HTMLPurifier_AttrDef_CSS_Length,
            new HTMLPurifier_AttrDef_CSS_Percentage,
            new HTMLPurifier_AttrDef_Enum(['auto', 'content', 'none', 'inherit', 'initial', 'unset']),
        ]);

        $css->info['flex'] = new HTMLPurifier_AttrDef_CSS_Multiple($flexPart, 3);
        $css->info['flex-flow'] = new SafeCssValue;

        $css->info['justify-content'] = $enum([
            'flex-start', 'flex-end', 'center', 'space-between', 'space-around',
            'space-evenly', 'start', 'end', 'left', 'right', 'normal', 'stretch',
            'inherit', 'initial', 'unset',
        ]);

        $css->info['align-items'] =
        $css->info['align-self'] = $enum([
            'flex-start', 'flex-end', 'center', 'baseline', 'stretch',
            'start', 'end', 'self-start', 'self-end', 'normal',
            'inherit', 'initial', 'unset',
        ]);

        $css->info['align-content'] = $enum([
            'flex-start', 'flex-end', 'center', 'space-between', 'space-around',
            'space-evenly', 'stretch', 'start', 'end', 'normal',
            'inherit', 'initial', 'unset',
        ]);

        $gap = new HTMLPurifier_AttrDef_CSS_Composite([
            new HTMLPurifier_AttrDef_CSS_Length,
            new HTMLPurifier_AttrDef_CSS_Percentage,
            new HTMLPurifier_AttrDef_Enum(['normal']),
        ]);

        $css->info['gap'] = new HTMLPurifier_AttrDef_CSS_Multiple($gap, 2);
        $css->info['row-gap'] =
        $css->info['column-gap'] = $gap;

        $css->info['grid'] = new SafeCssValue;
        $css->info['grid-template'] = new SafeCssValue;
        $css->info['grid-template-columns'] = new SafeCssValue;
        $css->info['grid-template-rows'] = new SafeCssValue;
        $css->info['grid-template-areas'] = new SafeCssValue;
        $css->info['grid-auto-columns'] = new SafeCssValue;
        $css->info['grid-auto-rows'] = new SafeCssValue;
        $css->info['grid-auto-flow'] = new SafeCssValue;
        $css->info['grid-column'] = new SafeCssValue;
        $css->info['grid-column-start'] = new SafeCssValue;
        $css->info['grid-column-end'] = new SafeCssValue;
        $css->info['grid-row'] = new SafeCssValue;
        $css->info['grid-row-start'] = new SafeCssValue;
        $css->info['grid-row-end'] = new SafeCssValue;
        $css->info['grid-area'] = new SafeCssValue;

        $css->info['box-sizing'] = $enum(['content-box', 'border-box']);
        $css->info['box-shadow'] = new SafeCssValue;
        $css->info['text-shadow'] = new SafeCssValue;

        $css->info['object-fit'] = $enum([
            'fill', 'contain', 'cover', 'none', 'scale-down', 'inherit', 'initial', 'unset',
        ]);

        $css->info['object-position'] = new HTMLPurifier_AttrDef_CSS_Multiple(
            new HTMLPurifier_AttrDef_CSS_Composite([
                new HTMLPurifier_AttrDef_CSS_Length,
                new HTMLPurifier_AttrDef_CSS_Percentage,
                new HTMLPurifier_AttrDef_Enum(['center', 'top', 'bottom', 'left', 'right']),
            ]),
            2
        );

        $css->info['transform'] = new SafeCssValue;

        $css->info['transform-origin'] = new HTMLPurifier_AttrDef_CSS_Multiple(
            new HTMLPurifier_AttrDef_CSS_Composite([
                new HTMLPurifier_AttrDef_CSS_Length,
                new HTMLPurifier_AttrDef_CSS_Percentage,
                new HTMLPurifier_AttrDef_Enum(['center', 'top', 'bottom', 'left', 'right']),
            ]),
            3
        );

        $css->info['transition'] = new SafeCssValue;

        $css->info['word-break'] = $enum([
            'normal', 'break-all', 'keep-all', 'break-word', 'inherit', 'initial', 'unset',
        ]);

        $css->info['overflow-wrap'] = $enum([
            'normal', 'break-word', 'anywhere', 'inherit', 'initial', 'unset',
        ]);

        $css->info['text-overflow'] = $enum([
            'clip', 'ellipsis', 'inherit', 'initial', 'unset',
        ]);

        $css->info['cursor'] = $enum([
            'auto', 'default', 'none', 'context-menu', 'help', 'pointer', 'progress',
            'wait', 'cell', 'crosshair', 'text', 'vertical-text', 'alias', 'copy',
            'move', 'no-drop', 'not-allowed', 'grab', 'grabbing', 'all-scroll',
            'col-resize', 'row-resize', 'n-resize', 'e-resize', 's-resize', 'w-resize',
            'ne-resize', 'nw-resize', 'se-resize', 'sw-resize', 'ew-resize', 'ns-resize',
            'nesw-resize', 'nwse-resize', 'zoom-in', 'zoom-out', 'inherit', 'initial', 'unset',
        ]);
    }
}
