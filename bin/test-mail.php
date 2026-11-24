<?php

/**
 * Send a test notification e-mail built from a stored trap (uses the mail settings of config.php).
 *
 *   php bin/test-mail.php <email> [trap-id]      send (default: latest stored trap)
 *   php bin/test-mail.php --html [trap-id]       print the HTML body instead of sending
 */

declare(strict_types=1);

use Snmptraps\Database;
use Snmptraps\Notify\MailChannel;
use Snmptraps\Notify\Message;
use Snmptraps\Trap\TrapParser;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$to = $argv[1] ?? '';
if ($to === '' || ($to !== '--html' && !filter_var($to, FILTER_VALIDATE_EMAIL))) {
    fwrite(STDERR, "usage: php bin/test-mail.php <email>|--html [trap-id]\n");
    exit(2);
}

$config = require __DIR__ . '/../src/bootstrap.php';
$db = Database::fromConfig($config);

$id = (int) ($argv[2] ?? 0);
$row = $id > 0
    ? $db->find('traps', $id)
    : $db->one('SELECT * FROM `traps` ORDER BY `id` DESC LIMIT 1');
if ($row === null) {
    fwrite(STDERR, "trap not found\n");
    exit(1);
}

// rebuild the Trap object the receiver would have produced from the stored raw trap
$definitions = $db->all('SELECT `oid`, `severity` FROM `severity_definitions` ORDER BY `id`');
$lines = array_map(static fn ($l) => $l . "\n", explode("\n", rtrim((string) $row['raw'], "\n")));
$trap = (new TrapParser($definitions))->parse($lines);
$message = new Message($trap, (int) $row['id'], $config->url);
$channel = new MailChannel($config);

if ($to === '--html') {
    echo $channel->html($message);
    exit(0);
}
$channel->send($message, [['email' => $to, 'real_name' => '']]);
echo "Sent trap #{$row['id']} ({$trap->hostname} - {$trap->message}) to $to\n";
