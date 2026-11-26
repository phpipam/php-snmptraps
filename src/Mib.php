<?php

declare(strict_types=1);

namespace Snmptraps;

/**
 * Reads notification definitions (NOTIFICATION-TYPE / TRAP-TYPE) from MIB files.
 * Only plain file names inside the configured MIB directories are accepted; if a name
 * exists in several directories, the first directory wins.
 */
final class Mib
{
    /** '' = no extension, as used by the downloaded IETF / IANA MIBs */
    private const EXTENSIONS = ['my', 'txt', 'mib', ''];
    private const MAX_FILE_SIZE = 8 * 1024 * 1024;

    /** @param list<string> $directories with trailing slash */
    public function __construct(private readonly array $directories)
    {
    }

    /** @return list<string> */
    public function directories(): array
    {
        return $this->directories;
    }

    /** @return list<array{file:string,size:int}> */
    public function files(): array
    {
        $out = [];
        foreach ($this->directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $f) {
                if (!isset($out[$f]) && self::isMibName($f) && is_file($dir . $f)) {
                    $out[$f] = ['file' => $f, 'size' => (int) filesize($dir . $f)];
                }
            }
        }
        ksort($out, SORT_STRING);
        return array_values($out);
    }

    /**
     * Resolve a user-supplied file name to a path inside the MIB directories.
     */
    public function path(string $file): ?string
    {
        $file = basename($file);
        if (!self::isMibName($file)) {
            return null;
        }
        foreach ($this->directories as $dir) {
            if (is_file($dir . $file)) {
                return $dir . $file;
            }
        }
        return null;
    }

    private static function isMibName(string $file): bool
    {
        return $file !== '' && $file[0] !== '.' && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    public function read(string $file): ?string
    {
        $path = $this->path($file);
        if ($path === null || filesize($path) > self::MAX_FILE_SIZE) {
            return null;
        }
        $text = file_get_contents($path);
        return $text === false ? null : $text;
    }

    /** Module name from "MODULE-NAME DEFINITIONS ::= BEGIN" */
    public static function moduleName(string $text): ?string
    {
        return preg_match('/^\s*([A-Za-z][\w-]*)\s+DEFINITIONS\s*::=\s*BEGIN/m', $text, $m) ? $m[1] : null;
    }

    /** @return list<string> notification names defined in the MIB text */
    public static function notifications(string $text): array
    {
        preg_match_all('/^\s*([a-zA-Z][\w-]*)\s+(?:NOTIFICATION-TYPE|TRAP-TYPE)\b/m', $text, $m);
        return array_values(array_unique($m[1]));
    }

    /**
     * @return array{objects:list<string>, description:string}|null
     */
    public static function notification(string $text, string $name): ?array
    {
        $re = '/^\s*' . preg_quote($name, '/') . '\s+(?:NOTIFICATION-TYPE|TRAP-TYPE)\b(.*?)::=/ms';
        if (!preg_match($re, $text, $m)) {
            return null;
        }
        $body = $m[1];
        $objects = [];
        if (preg_match('/(?:OBJECTS|VARIABLES)\s*\{([^}]*)\}/s', $body, $o)) {
            $objects = array_values(array_filter(array_map('trim', explode(',', $o[1]))));
        }
        $description = '';
        if (preg_match('/DESCRIPTION\s*"(.*?)"/s', $body, $d)) {
            $description = trim(preg_replace('/[ \t]+/', ' ', preg_replace('/[ \t]*\n[ \t]*/', "\n", $d[1]) ?? '') ?? '');
        }
        return ['objects' => $objects, 'description' => $description];
    }

    /**
     * Look up a trap OID like "IF-MIB::linkDown".
     *
     * @return array{file:string, objects:list<string>, description:string}|null
     */
    public function lookup(string $oid): ?array
    {
        $pos = strpos($oid, '::');
        if ($pos === false) {
            return null;
        }
        $module = substr($oid, 0, $pos);
        $name = substr($oid, $pos + 2);
        $file = $this->fileForModule($module);
        if ($file === null) {
            return null;
        }
        $text = $this->read($file);
        $n = $text === null ? null : self::notification($text, $name);
        return $n === null ? ['file' => $file, 'objects' => [], 'description' => ''] : ['file' => $file] + $n;
    }

    private function fileForModule(string $module): ?string
    {
        foreach (self::EXTENSIONS as $ext) {
            $file = $ext === '' ? $module : "$module.$ext";
            if ($this->path($file) !== null) {
                return $file;
            }
        }
        return $this->moduleIndex()[$module] ?? null;
    }

    /**
     * module => file index, cached in the system temp dir and rebuilt when a MIB dir changes.
     *
     * @return array<string,string>
     */
    private function moduleIndex(): array
    {
        $cache = sys_get_temp_dir() . '/snmptraps-mib-index-' . md5(implode(':', $this->directories)) . '.json';
        $mtime = implode(':', array_map(static fn (string $d): int => is_dir($d) ? (int) filemtime($d) : 0, $this->directories));
        if (is_file($cache)) {
            $data = json_decode((string) file_get_contents($cache), true);
            if (is_array($data) && ($data['mtime'] ?? null) === $mtime && is_array($data['index'] ?? null)) {
                return $data['index'];
            }
        }
        $index = [];
        foreach ($this->files() as $f) {
            $fh = @fopen((string) $this->path($f['file']), 'rb');
            if ($fh === false) {
                continue;
            }
            $head = (string) fread($fh, 16384);
            fclose($fh);
            $module = self::moduleName($head);
            if ($module !== null && !isset($index[$module])) {
                $index[$module] = $f['file'];
            }
        }
        @file_put_contents($cache, json_encode(['mtime' => $mtime, 'index' => $index]), LOCK_EX);
        return $index;
    }
}
