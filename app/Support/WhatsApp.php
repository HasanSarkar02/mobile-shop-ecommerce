<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for WhatsApp deep-link generation.
 *
 * Accepts anything a merchant might paste into Theme Settings:
 * "01712345678", "+8801712345678", "8801712345678", "wa.me/8801712345678",
 * or "https://wa.me/8801712345678" — and normalizes it into an
 * https://wa.me/<digits> URL. Returns null when nothing usable is configured
 * so callers can gracefully hide the widget.
 */
final class WhatsApp
{
    public static function url(?string $configured, ?string $message = null): ?string
    {
        $number = self::normalize($configured);

        if ($number === null) {
            return null;
        }

        $url = "https://wa.me/{$number}";

        return $message !== null && trim($message) !== ''
            ? $url.'?text='.rawurlencode(trim($message))
            : $url;
    }

    public static function normalize(?string $configured): ?string
    {
        if ($configured === null || trim($configured) === '') {
            return null;
        }

        $value = trim($configured);

        // Strip scheme/host prefixes so wa.me links pasted wholesale still work.
        if (preg_match('#wa\.me/(\d+)#i', $value, $matches) === 1) {
            $digits = $matches[1];
        } else {
            $digits = preg_replace('/\D+/', '', $value) ?? '';
        }

        if ($digits === '') {
            return null;
        }

        // Normalize local BD numbers to the international 880 prefix.
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '880'.substr($digits, 1);
        }

        return $digits !== '' ? $digits : null;
    }
}
