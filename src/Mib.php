<?php

declare(strict_types=1);

namespace Snmptraps;

/**
 * Reads notification definitions (NOTIFICATION-TYPE / TRAP-TYPE) from MIB files.
 * Only plain file names inside the configured MIB directory are accepted.
 */
final class Mib
{
    private const EXTENSIONS = ['my', 'txt', 'mib'];
    private const MAX_FILE_SIZE = 8 * 1024 * 1024;

    public function __construct(private readonly string $directory)
    {
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /** @return list<array{file:string,size:int}> */
    public function files(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }
        $out = [];
        foreach (scandir($this->directory) ?: [] as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if ($f[0] !== '.' && in_array($ext, self::EXTENSIONS, true) && is_file($this->directory . $f)) {
                $out[] = ['file' => $f, 'size' => (int) filesize($this->directory . $f)];
            }
        }
        return $out;
    }

    /**
     * Resolve a user-supplied file name to a path inside the MIB directory.
     */
    public function path(string $file): ?string
    {
        $file = basename($file);
        if ($file === '' || $file[0] === '.' || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            return null;
        }
        $path = $this->directory . $file;
        return is_file($path) ? $path : null;
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
            $description = trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\s*\n\s*/', "\n", $d[1]) ?? '') ?? '');
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
            if ($this->path("$module.$ext") !== null) {
                return "$module.$ext";
            }
        }
        return $this->moduleIndex()[$module] ?? null;
    }

    /**
     * module => file index, cached in the system temp dir and rebuilt when the MIB dir changes.
     *
     * @return array<string,string>
     */
    private function moduleIndex(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }
        $cache = sys_get_temp_dir() . '/snmptraps-mib-index-' . md5($this->directory) . '.json';
        $mtime = (int) filemtime($this->directory);
        if (is_file($cache)) {
            $data = json_decode((string) file_get_contents($cache), true);
            if (is_array($data) && ($data['mtime'] ?? null) === $mtime && is_array($data['index'] ?? null)) {
                return $data['index'];
            }
        }
        $index = [];
        foreach ($this->files() as $f) {
            $fh = @fopen($this->directory . $f['file'], 'rb');
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
