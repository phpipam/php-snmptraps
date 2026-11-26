<?php

/**
 * Lock-out recovery: removes the two-factor authentication (TOTP) of a user and allows
 * password sign-in again (passwordless off). With $require_2fa the user sets up a new
 * authenticator app at the next sign-in.
 *
 *   php bin/reset-2fa.php <username>              reset TOTP, allow password sign-in
 *   php bin/reset-2fa.php <username> --passkeys   also remove all passkeys of the user
 *   php bin/reset-2fa.php <username> --password   also set a new password (asked, local users)
 */

declare(strict_types=1);

use Snmptraps\Auth;
use Snmptraps\Database;
use Snmptraps\Schema;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$args = array_slice($argv, 1);
$flags = array_filter($args, static fn ($a) => str_starts_with($a, '--'));
$names = array_values(array_diff($args, $flags));
if (count($names) !== 1 || array_diff($flags, ['--passkeys', '--password']) !== []) {
    echo preg_replace('/^ \* ?/m', '', explode('*/', explode('/**', file_get_contents(__FILE__), 2)[1], 2)[0]);
    exit(1);
}

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$db = Database::fromConfig($config);
if (!(new Schema($db, $config->db['name']))->has('1.1.0')) {
    fwrite(STDERR, "Migration 1.1.0 is not applied - there is no 2FA to reset (php bin/migrate.php)." . PHP_EOL);
    exit(1);
}
$user = $db->one('SELECT * FROM `users` WHERE `username` = ?', [$names[0]]);
if ($user === null) {
    fwrite(STDERR, "User {$names[0]} not found" . PHP_EOL);
    exit(1);
}
$id = (int) $user['id'];

$db->run('UPDATE `users` SET `totp_secret` = NULL, `totp_last_step` = NULL, `passwordless` = 0 WHERE `id` = ?', [$id]);
echo "{$user['username']}: two-factor authentication removed, password sign-in allowed" . PHP_EOL;

if (in_array('--passkeys', $flags, true)) {
    $n = $db->run('DELETE FROM `passkeys` WHERE `user_id` = ?', [$id])->rowCount();
    echo "{$user['username']}: $n passkey(s) removed" . PHP_EOL;
}

if (in_array('--password', $flags, true)) {
    if ($user['auth_method'] !== 'local') {
        fwrite(STDERR, "{$user['username']} signs in with {$user['auth_method']} - the password is managed there" . PHP_EOL);
        exit(1);
    }
    $read = static function (string $prompt): string {
        fwrite(STDOUT, $prompt);
        if (stream_isatty(STDIN)) {
            system('stty -echo');
        }
        $v = rtrim((string) fgets(STDIN), "\r\n");
        if (stream_isatty(STDIN)) {
            system('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }
        return $v;
    };
    $pw = $read('New password (min 8 characters): ');
    if (mb_strlen($pw) < 8 || $pw !== $read('Repeat: ')) {
        fwrite(STDERR, 'Passwords do not match or are too short - password not changed' . PHP_EOL);
        exit(1);
    }
    $db->run('UPDATE `users` SET `password` = ? WHERE `id` = ?', [Auth::hashPassword($pw), $id]);
    echo "{$user['username']}: password changed" . PHP_EOL;
}
