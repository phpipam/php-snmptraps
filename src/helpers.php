<?php

declare(strict_types=1);

/**
 * Small global helpers used by views and controllers.
 */

/**
 * HTML-escape any scalar for output in element content or quoted attributes.
 */
function e(mixed $value): string
{
    if ($value === null || $value === false) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape multi-line text and convert newlines to <br>.
 */
function e_nl(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $lines = array_filter(preg_split('/\r\n|\r|\n/', $value) ?: [], static fn ($l) => trim($l) !== '');
    return implode('<br>', array_map('e', $lines));
}

/**
 * URL-safe base64 used for message identifiers in URLs.
 * Accepts legacy standard base64 links (with +, / and =) when decoding.
 */
function b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function b64url_decode(string $value): string|false
{
    $value = strtr($value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad) {
        $value .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($value, true);
}

/**
 * Escape text that was hard-wrapped (e.g. MIB DESCRIPTION): single newlines become
 * spaces, blank lines become paragraph breaks.
 */
function e_text(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }
    $paras = preg_split('/\n\s*\n/', trim($value)) ?: [];
    return implode('<br><br>', array_map(static fn ($p) => e(preg_replace('/\s+/', ' ', $p)), $paras));
}
