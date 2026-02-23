<?php
declare(strict_types=1);

namespace Solas\Portal\Core;

use Solas\Portal\Contracts\Module;

defined('ABSPATH') || exit;

final class Plugin {

    /** @var Module[] */
    private array $modules;

    /**
     * @param Module[] $modules
     */
    public function __construct(array $modules) {
        $this->modules = $modules;
    }

    public function register(): void {
        foreach ($this->modules as $module) {
            if ($module instanceof Module) {
                $module->register();
            }
        }
    }
}
