<?php

declare(strict_types=1);

namespace Snmptraps;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use OTPHP\TOTP as Otp;

/**
 * Time-based one-time passwords (RFC 6238: SHA-1, 6 digits, 30 s) for two-factor
 * authentication - the defaults every authenticator app supports.
 */
final class Totp
{
    /** accepted clock drift in time steps before / after now */
    private const WINDOW = 1;

    /** new random base32 secret (160 bit) */
    public static function newSecret(): string
    {
        return Otp::generate(null, 20)->getSecret();
    }

    /** otpauth:// URI for authenticator apps */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $otp = Otp::createFromSecret($secret);
        $otp->setLabel($account);
        $otp->setIssuer($issuer);
        return $otp->getProvisioningUri();
    }

    /** QR code of the URI as an SVG data: URI (for <img src>) */
    public static function qrCode(string $uri): string
    {
        return (new QRCode(new QROptions([
            'outputBase64'    => true,
            'svgAddXmlHeader' => false,
            'addQuietzone'    => true,
            'quietzoneSize'   => 2,
            'drawLightModules' => false,
            'connectPaths'    => true,
        ])))->render($uri);
    }

    /** secret in groups of four for manual entry */
    public static function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * Checks a code against the secret. Each code is accepted only once: the matched time
     * step must be newer than $lastStep (users.totp_last_step).
     *
     * @return int|null matched time step (store it as the new last step), null = invalid
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code) || $secret === '') {
            return null;
        }
        $otp = Otp::createFromSecret($secret);
        $period = $otp->getPeriod();
        $now ??= time();
        $match = null;
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $t = $now + $i * $period;
            // compare all candidates (no early exit), then pick the matching step
            if (hash_equals($otp->at($t), $code)) {
                $match = intdiv($t, $period);
            }
        }
        if ($match === null || ($lastStep !== null && $match <= $lastStep)) {
            return null;
        }
        return $match;
    }
}
