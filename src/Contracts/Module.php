<?php
declare(strict_types=1);

namespace Solas\Portal\Contracts;

defined('ABSPATH') || exit;

/**
 * Simple module contract (dependency-free).
 * Modules register hooks/filters in register().
 */
interface Module {
    public function register(): void;
}
