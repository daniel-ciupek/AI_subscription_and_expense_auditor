<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reduce a free-form bank description to its merchant-identifying core.
 *
 * Bank statements pad descriptions with timestamps, terminal IDs and order
 * numbers (e.g. "BIEDRONKA 1234 POZNAN 15:42:09" / "BIEDRONKA 9999 KRAKOW")
 * — the digits change every transaction but the merchant is the same. This
 * helper strips that noise so we can group recurring charges and reuse the
 * AI categorization across runs.
 */
final class TransactionNormalizer
{
    public static function normalize(string $description): string
    {
        $lower = mb_strtolower($description);
        // Strip everything but unicode letters and whitespace, collapse runs.
        $stripped = preg_replace('/[^\p{L}\s]/u', ' ', $lower) ?? $lower;
        $collapsed = preg_replace('/\s+/u', ' ', $stripped) ?? $stripped;

        return trim($collapsed);
    }
}
