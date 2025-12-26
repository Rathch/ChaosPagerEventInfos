<?php

namespace ChaosPagerEventInfos;

/**
 * AsciiSanitizer - Converts non-ASCII characters to ASCII equivalents
 *
 * Sanitizes text to ensure only ASCII characters are present, as required by DAPNET API.
 * Uses native PHP functions for character conversion.
 */
class AsciiSanitizer
{
    /**
     * Sanitizes text to ASCII-only characters
     *
     * Converts common German umlauts and other non-ASCII characters to ASCII equivalents.
     * Removes any remaining non-ASCII characters.
     *
     * @param string $text Input text (may contain non-ASCII characters)
     * @return string ASCII-only text
     */
    public static function sanitize(string $text): string
    {
        // First, try to transliterate using iconv if available
        if (function_exists('iconv')) {
            // Try to transliterate to ASCII
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($transliterated !== false) {
                $text = $transliterated;
            }
        }

        // Manual mapping for common German characters if iconv didn't work or wasn't available
        $replacements = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ß' => 'ss', 'ẞ' => 'SS',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
        ];

        $text = strtr($text, $replacements);

        // Remove any remaining non-ASCII characters using regex
        // Only allow ASCII characters (0x00-0x7F)
        $text = preg_replace('/[^\x00-\x7F]/', '', $text);

        // preg_replace can return null on error, ensure we return string
        return $text ?? '';
    }
}
