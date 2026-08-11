<?php

namespace App\Support\Purifier;

/**
 * Conservative CSS value validator for properties HTMLPurifier does not
 * know natively (flex, grid, gap, box-shadow, transform, ...).
 *
 * The value is kept only when it consists of plain CSS tokens
 * (lengths, colors, numbers, keywords, separated by spaces/commas).
 * Known CSS/JS injection tokens are rejected outright.
 */
class SafeCssValue extends \HTMLPurifier_AttrDef
{
    /**
     * @var string[]
     */
    protected $forbidden = [
        'url(',
        'expression',
        'javascript:',
        'vbscript:',
        'data:',
        'behavior',
        '-moz-binding',
        '@import',
        '<',
        '>',
        '"',
        "'",
        '`',
        '\\',
        '&',
    ];

    /**
     * @param string $string
     * @param \HTMLPurifier_Config $config
     * @param \HTMLPurifier_Context $context
     * @return bool|string
     */
    public function validate($string, $config, $context)
    {
        $string = $this->parseCDATA($string);
        $string = preg_replace('#/\*.*?\*/#s', '', $string);
        $string = trim($string);

        if ($string === '' || strlen($string) > 512) {
            return false;
        }

        $lower = strtolower($string);
        foreach ($this->forbidden as $needle) {
            if (strpos($lower, $needle) !== false) {
                return false;
            }
        }

        if (preg_match('/^[a-zA-Z0-9#%.,:()\/+\-!*\s]+$/', $string) !== 1) {
            return false;
        }

        return $string;
    }
}
