<?php

declare(strict_types=1);

namespace Snmptraps;

use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use RuntimeException;
use stdClass;

/**
 * Passkeys (WebAuthn discoverable credentials): registration, sign-in and storage (table passkeys).
 *
 * Passkeys are bound to the relying party id = the host name of the UI ($passkey_rp_id or the
 * request host) and only work over HTTPS (or on localhost). User verification (PIN / biometrics)
 * is required, so a passkey sign-in is a complete two-factor login on its own.
 *
 * The challenge of a ceremony is kept in the session (single use, CHALLENGE_TTL).
 * Binary values are exchanged with the browser as base64url (public/js/passkey.js).
 */
final class Passkeys
{
    /** browser ceremony timeout (seconds) */
    private const TIMEOUT = 120;
    private const CHALLENGE_TTL = 300;
    public const MAX_PER_USER = 20;

    public function __construct(private readonly Config $config, private readonly Database $db)
    {
    }

    private static function requestHost(): string
    {
        $host = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''), PHP_URL_HOST));
        return trim($host, '[].');
    }

    /** host name the passkeys are bound to, null when the UI is opened by an IP address */
    public function rpId(): ?string
    {
        if ($this->config->passkeyRpId !== null) {
            return $this->config->passkeyRpId;
        }
        $host = self::requestHost();
        return $host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false ? null : $host;
    }

    /** why passkeys cannot be used on this request (plain text), null = available */
    public function unavailableReason(): ?string
    {
        $rp = $this->rpId();
        if ($rp === null) {
            return 'Passkeys need a host name - this page was opened by its IP address.';
        }
        $host = self::requestHost();
        if ($host !== $rp && !str_ends_with($host, '.' . $rp)) {
            return "Passkeys are configured for $rp (\$passkey_rp_id), but this page was opened as $host.";
        }
        if ($rp !== 'localhost' && !Auth::isHttps()) {
            return 'Passkeys need HTTPS - this page was opened over plain HTTP.';
        }
        return null;
    }

    private function webauthn(): WebAuthn
    {
        $rp = $this->rpId();
        if ($rp === null || $this->unavailableReason() !== null) {
            throw new RuntimeException($this->unavailableReason() ?? 'Passkeys are not available');
        }
        // all attestation formats are accepted (attestation itself is not requested, see below)
        return new WebAuthn($this->config->title, $rp, null, true);
    }

    // ---- registration

    /**
     * Options for navigator.credentials.create(): resident key + user verification required.
     */
    public function registrationOptions(array $user): stdClass
    {
        $userId = (int) $user['id'];
        if ($this->count($userId) >= self::MAX_PER_USER) {
            throw new RuntimeException('Too many passkeys (max ' . self::MAX_PER_USER . ')');
        }
        $handle = (string) ($user['webauthn_handle'] ?? '');
        if (!preg_match('/^[0-9a-f]{32}$/', $handle)) {
            // random user handle - the WebAuthn user id must not contain personal data
            $handle = bin2hex(random_bytes(16));
            $this->db->run('UPDATE `users` SET `webauthn_handle` = ? WHERE `id` = ?', [$handle, $userId]);
        }
        $wa = $this->webauthn();
        $exclude = $this->db->column('SELECT `credential_id` FROM `passkeys` WHERE `user_id` = ?', [$userId]);
        $name = (string) $user['username'];
        $args = $wa->getCreateArgs((string) hex2bin($handle), $name, (string) ($user['real_name'] ?: $name), self::TIMEOUT, true, true, null, $exclude);
        // no attestation (no "allow the site to see your security key" prompt); "exts" is not a WebAuthn extension
        $args->publicKey->attestation = 'none';
        $args->publicKey->extensions = new stdClass();
        $args->publicKey->extensions->credProps = true;
        $this->storeChallenge('create', $wa->getChallenge(), $userId);
        return $args;
    }

    /**
     * Verifies the browser's answer to registrationOptions() and stores the passkey.
     *
     * @param array<string,mixed> $credential {id, response: {clientDataJSON, attestationObject}} base64url
     */
    public function register(array $user, string $name, array $credential): int
    {
        $userId = (int) $user['id'];
        $challenge = $this->takeChallenge('create', $userId);
        $response = is_array($credential['response'] ?? null) ? $credential['response'] : [];
        try {
            $data = $this->webauthn()->processCreate(
                self::bin($response['clientDataJSON'] ?? null),
                self::bin($response['attestationObject'] ?? null),
                $challenge,
                true,   // user verification
                true,   // user presence
                false,  // no root certificates configured - attestation is not checked
                false,
            );
        } catch (WebAuthnException $e) {
            throw new RuntimeException('Passkey verification failed: ' . $e->getMessage(), 0, $e);
        }
        $credentialId = self::bytes($data->credentialId);
        if ($credentialId === '' || strlen($credentialId) > 1023) {
            throw new RuntimeException('Invalid credential id');
        }
        if ($this->db->value('SELECT COUNT(*) FROM `passkeys` WHERE `credential_id` = ?', [$credentialId]) > 0) {
            throw new RuntimeException('This passkey is already registered');
        }
        $aaguid = bin2hex(self::bytes($data->AAGUID ?? ''));   // authenticator model, all zero = unknown
        return $this->db->insert('passkeys', [
            'user_id'       => $userId,
            'credential_id' => $credentialId,
            'public_key'    => (string) $data->credentialPublicKey,
            'sign_count'    => (int) ($data->signatureCounter ?? 0),
            'aaguid'        => strlen($aaguid) === 32 && trim($aaguid, '0') !== '' ? preg_replace('/^(.{8})(.{4})(.{4})(.{4})(.{12})$/', '$1-$2-$3-$4-$5', $aaguid) : null,
            'synced'        => !empty($data->isBackupEligible) ? 1 : 0,
            'name'          => mb_substr(trim($name) !== '' ? trim($name) : 'Passkey', 0, 64),
        ]);
    }

    // ---- sign-in

    /** Options for navigator.credentials.get() - discoverable credentials, no user name needed */
    public function loginOptions(): stdClass
    {
        $wa = $this->webauthn();
        $args = $wa->getGetArgs([], self::TIMEOUT, true, true, true, true, true, true);
        $this->storeChallenge('get', $wa->getChallenge(), null);
        return $args;
    }

    /**
     * Verifies a sign-in assertion.
     *
     * @param array<string,mixed> $credential {id, response: {clientDataJSON, authenticatorData, signature, userHandle}} base64url
     * @return array<string,mixed> the user row
     */
    public function authenticate(array $credential): array
    {
        $challenge = $this->takeChallenge('get', null);
        $response = is_array($credential['response'] ?? null) ? $credential['response'] : [];
        $pk = $this->db->one('SELECT * FROM `passkeys` WHERE `credential_id` = ? LIMIT 1', [self::bin($credential['id'] ?? null)]);
        if ($pk === null) {
            throw new RuntimeException('This passkey is not registered (any more). Sign in with your password and add it again under Security.');
        }
        $user = $this->db->find('users', (int) $pk['user_id']) ?? throw new RuntimeException('The user of this passkey does not exist');
        $handle = self::bin($response['userHandle'] ?? '', true);
        if ($handle !== '' && !hash_equals((string) $user['webauthn_handle'], bin2hex($handle))) {
            throw new RuntimeException('Passkey does not belong to this user');
        }
        $wa = $this->webauthn();
        try {
            $wa->processGet(
                self::bin($response['clientDataJSON'] ?? null),
                self::bin($response['authenticatorData'] ?? null),
                self::bin($response['signature'] ?? null),
                (string) $pk['public_key'],
                $challenge,
                (int) $pk['sign_count'],
                true,   // user verification (PIN / biometrics) - replaces the second factor
                true,
            );
        } catch (WebAuthnException $e) {
            throw new RuntimeException('Passkey verification failed: ' . $e->getMessage(), 0, $e);
        }
        $this->db->run(
            'UPDATE `passkeys` SET `sign_count` = ?, `last_used` = NOW() WHERE `id` = ?',
            [$wa->getSignatureCounter() ?? (int) $pk['sign_count'], (int) $pk['id']],
        );
        return $user;
    }

    // ---- storage

    /** @return list<array<string,mixed>> passkeys of a user, without key material */
    public function forUser(int $userId): array
    {
        return $this->db->all(
            'SELECT `id`, `name`, `aaguid`, `synced`, `created`, `last_used` FROM `passkeys` WHERE `user_id` = ? ORDER BY `created`',
            [$userId],
        );
    }

    public function count(int $userId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM `passkeys` WHERE `user_id` = ?', [$userId]);
    }

    /** removes a passkey; passwordless login is switched off with the last one */
    public function delete(int $userId, int $id): bool
    {
        $n = $this->db->run('DELETE FROM `passkeys` WHERE `id` = ? AND `user_id` = ?', [$id, $userId])->rowCount();
        if ($n > 0 && $this->count($userId) === 0) {
            $this->db->run('UPDATE `users` SET `passwordless` = 0 WHERE `id` = ?', [$userId]);
        }
        return $n > 0;
    }

    public function deleteAll(int $userId): int
    {
        $this->db->run('UPDATE `users` SET `passwordless` = 0 WHERE `id` = ?', [$userId]);
        return $this->db->run('DELETE FROM `passkeys` WHERE `user_id` = ?', [$userId])->rowCount();
    }

    // ---- helpers

    private function storeChallenge(string $type, ByteBuffer $challenge, ?int $userId): void
    {
        $_SESSION['webauthn'] = ['type' => $type, 'challenge' => base64_encode($challenge->getBinaryString()), 'user' => $userId, 'time' => time()];
    }

    private function takeChallenge(string $type, ?int $userId): ByteBuffer
    {
        $c = $_SESSION['webauthn'] ?? null;
        unset($_SESSION['webauthn']);
        if (!is_array($c) || ($c['type'] ?? '') !== $type || ($c['user'] ?? null) !== $userId || (int) ($c['time'] ?? 0) < time() - self::CHALLENGE_TTL) {
            throw new RuntimeException('Passkey request expired, please try again');
        }
        return new ByteBuffer((string) base64_decode((string) $c['challenge'], true));
    }

    /** base64url from the browser -> binary */
    private static function bin(mixed $value, bool $optional = false): string
    {
        $bin = is_string($value) && preg_match('/^[A-Za-z0-9_-]*={0,2}$/', $value) ? b64url_decode(rtrim($value, '=')) : false;
        if ($bin === false || ($bin === '' && !$optional)) {
            if ($optional && ($value === null || $value === '')) {
                return '';
            }
            throw new RuntimeException('Invalid passkey data');
        }
        return $bin;
    }

    private static function bytes(mixed $v): string
    {
        return $v instanceof ByteBuffer ? $v->getBinaryString() : (string) $v;
    }
}
