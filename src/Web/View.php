<?php

declare(strict_types=1);

namespace Snmptraps\Web;

/**
 * Plain PHP templates from views/. Templates receive their variables plus $app.
 * All dynamic output in templates must go through e() / e_nl().
 */
final class View
{
    /** Tabler theme defaults (dark, azure accent, gray base) - set on <html> of every page */
    public const THEME = 'data-bs-theme="dark" data-bs-theme-primary="azure" data-bs-theme-base="gray"';

    public function __construct(private readonly string $dir, private readonly App $app)
    {
    }

    public function render(string $template, array $vars = []): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View $template not found");
        }
        $vars['app'] = $this->app;
        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        })($file, $vars);
    }

    /** Bootstrap alert box */
    public static function alert(string $type, string $html): string
    {
        $type = in_array($type, ['success', 'info', 'warning', 'danger'], true) ? $type : 'info';
        return "<div class='alert alert-$type'>$html</div>";
    }
}
