<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use RuntimeException;

/**
 * Minimal HTTP client on top of PHP streams (no curl extension required).
 */
final class HttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string}
     */
    public static function request(string $method, string $url, string $body = '', array $headers = [], int $timeout = 10): array
    {
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "$k: $v";
        }
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headerLines),
                'content'       => $body,
                'timeout'       => $timeout,
                'ignore_errors' => true,
                'user_agent'    => 'php-snmptraps',
            ],
        ]);

        $fp = @fopen($url, 'rb', false, $context);
        if ($fp === false) {
            $err = error_get_last()['message'] ?? 'unknown error';
            throw new RuntimeException("HTTP $method " . self::redact($url) . " failed: $err");
        }
        $meta = stream_get_meta_data($fp);
        $responseBody = (string) stream_get_contents($fp);
        fclose($fp);

        $status = 0;
        foreach ((array) ($meta['wrapper_data'] ?? []) as $h) {
            if (is_string($h) && preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1]; // last status line wins (redirects)
            }
        }
        return ['status' => $status, 'body' => $responseBody];
    }

    /**
     * @param array<string,scalar> $fields
     * @return array{status:int, body:string}
     */
    public static function postForm(string $url, array $fields): array
    {
        return self::request('POST', $url, http_build_query($fields), ['Content-Type' => 'application/x-www-form-urlencoded']);
    }

    /** @return array{status:int, body:string} */
    public static function postJson(string $url, array $payload): array
    {
        return self::request('POST', $url, (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ['Content-Type' => 'application/json']);
    }

    /** Hide query strings / tokens in error messages */
    private static function redact(string $url): string
    {
        $p = parse_url($url);
        return ($p['scheme'] ?? 'http') . '://' . ($p['host'] ?? '?') . (isset($p['port']) ? ':' . $p['port'] : '') . ($p['path'] ?? '');
    }
}
