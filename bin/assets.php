<?php

/**
 * Copies the front-end files of the npm-asset/* Composer packages into public/
 * (mapping: "extra.assets" in composer.json). Runs after composer install / update;
 * manually: composer assets
 *
 * The Tabler Icons webfont files referenced by the copied CSS (url("./fonts/...")) are
 * copied along. public/ is committed, so the web server never needs vendor/npm-asset/.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents("$root/composer.json"), true, flags: JSON_THROW_ON_ERROR);
$vendor = "$root/vendor";
$copied = 0;

$copy = static function (string $from, string $to) use (&$copied): void {
    if (!is_file($from)) {
        fwrite(STDERR, "ERROR: missing $from (run composer install)" . PHP_EOL);
        exit(1);
    }
    if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0755, true)) {
        fwrite(STDERR, "ERROR: cannot create " . dirname($to) . PHP_EOL);
        exit(1);
    }
    if (!is_file($to) || md5_file($from) !== md5_file($to)) {
        copy($from, $to) || exit(1);
        $copied++;
    }
};

foreach ($composer['extra']['assets'] ?? [] as $package => $files) {
    foreach ($files as $src => $dst) {
        $from = "$vendor/$package/$src";
        $to = "$root/$dst";
        $copy($from, $to);
        // fonts / images referenced relatively from a stylesheet
        if (str_ends_with($dst, '.css')) {
            preg_match_all('#url\(["\']?\./([^"\')?\#]+)#', (string) file_get_contents($from), $m);
            foreach (array_unique($m[1]) as $rel) {
                $copy(dirname($from) . "/$rel", dirname($to) . "/$rel");
            }
        }
    }
}
echo "assets: $copied file(s) updated in public/" . PHP_EOL;
