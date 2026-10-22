<?php

namespace App\Support;

/**
 * Kazakhstan phone numbers, stored as bare digits `7XXXXXXXXXX`.
 *
 * Owners type the number every possible way — `8 701…`, `+7 (701)…`, `701…` —
 * and the manager needs one canonical form to build a WhatsApp link from.
 */
class Phone
{
    /** Canonical shape after normalize(): country code 7 + ten digits. */
    public const PATTERN = '/^7\d{10}$/';

    /**
     * Strip formatting and fold the local trunk prefix 8 into the country
     * code. Anything that cannot become a number is returned as typed, so
     * validation rejects it instead of silently storing garbage.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return match (true) {
            strlen($digits) === 11 && $digits[0] === '8' => '7'.substr($digits, 1),
            strlen($digits) === 10 => '7'.$digits,
            $digits === '' => trim($raw),
            default => $digits,
        };
    }

    /** `77011234567` → `+7 701 123 45 67` for display in the panel. */
    public static function format(?string $phone): ?string
    {
        if ($phone === null || ! preg_match(self::PATTERN, $phone)) {
            return $phone;
        }

        return sprintf(
            '+7 %s %s %s %s',
            substr($phone, 1, 3),
            substr($phone, 4, 3),
            substr($phone, 7, 2),
            substr($phone, 9, 2),
        );
    }

    public static function whatsappUrl(?string $phone): ?string
    {
        return $phone ? 'https://wa.me/'.$phone : null;
    }
}
