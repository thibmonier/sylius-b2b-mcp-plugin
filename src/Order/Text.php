<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

/** Formatting of the values returned to the assistant. */
final class Text
{
    /** Plain text: tags, control and format characters removed, length capped. */
    public static function clean(?string $value, int $maxLength = 255): ?string
    {
        if (null === $value) {
            return null;
        }
        // Format characters (bidi overrides, zero-width) and unassigned or private ones vanish;
        // other controls (line breaks...) become a space.
        $value = (string) preg_replace('/[\p{Cf}\p{Co}\p{Cn}\p{Cs}]+/u', '', strip_tags($value));
        $value = trim((string) preg_replace('/\p{Cc}+/u', ' ', $value));

        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) . '…' : $value;
    }

    /** Amount in minor units as a decimal string, like the shop (two decimals). */
    public static function money(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    public static function date(?\DateTimeInterface $date): ?string
    {
        return $date?->format(\DateTimeInterface::ATOM);
    }

    private function __construct()
    {
    }
}
