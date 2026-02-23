<?php
declare(strict_types=1);

/**
 * Lightweight PSR-4 style autoloader for SOLAS Portal.
 *
 * Namespace root: Solas\Portal\
 * Directory:      /src/
 *
 * Notes
 * - This is intentionally dependency-free (no Composer) for activation safety.
 * - Only loads classes within the Solas\Portal\ namespace.
 */

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Solas\\Portal\\';
    $len = strlen($prefix);

    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = SOLAS_PORTAL_PATH . 'src/' . $relative . '.php';

    if (is_readable($file)) {
        require_once $file;
        return;
    }

    // Back-compat for historical folder casing (CPD namespace vs Cpd directory on Linux).
    // If the PSR-4 path isn't readable, try swapping only the first "CPD" segment.
    $alt = $file;
    $alt = str_replace(DIRECTORY_SEPARATOR . 'CPD' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'Cpd' . DIRECTORY_SEPARATOR, $alt);
    // Also cover forward-slash paths regardless of DIRECTORY_SEPARATOR.
    $alt = preg_replace('#/CPD/#', '/Cpd/', $alt);

    if ($alt && $alt !== $file && is_readable($alt)) {
        require_once $alt;
    }
});

