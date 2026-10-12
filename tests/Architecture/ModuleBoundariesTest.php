<?php

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ModuleBoundariesTest extends TestCase
{
    /** Directories every module may reference in any other module. */
    private const OPEN_SEGMENTS = ['Contracts', 'Events', 'Enums'];

    /** Modules that only read other modules' data and may import their models. */
    private const READ_SIDE_MODULES = ['Reporting', 'Notifications'];

    private const USER_MODEL = 'App\\Modules\\Identity\\Models\\User';

    /**
     * @return array<string, list<string>> offending file => referenced classes
     */
    private function violations(): array
    {
        $root = dirname(__DIR__, 2).'/app/Modules';
        $violations = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);
            [$module, $area] = array_pad(explode('/', $relative), 2, '');

            preg_match_all(
                '/App\\\\Modules\\\\(\w+)\\\\([\w\\\\]+)/',
                (string) file_get_contents($file->getPathname()),
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as [$full, $target, $path]) {
                if ($target === $module || $target === 'Shared' || $full === self::USER_MODEL) {
                    continue;
                }

                $segment = explode('\\', $path)[0];

                if (in_array($segment, self::OPEN_SEGMENTS, true)) {
                    continue;
                }

                if ($segment === 'Models' && ($area === 'Models' || in_array($module, self::READ_SIDE_MODULES, true))) {
                    continue;
                }

                $violations[$relative][] = $full;
            }
        }

        return array_map(fn (array $classes) => array_values(array_unique($classes)), $violations);
    }

    public function test_modules_only_touch_each_other_through_their_public_surface(): void
    {
        $this->assertSame([], $this->violations(), 'Module boundary violations found.');
    }

    public function test_type_folders_have_been_replaced_by_modules(): void
    {
        $app = dirname(__DIR__, 2).'/app';

        foreach (['Models', 'Services', 'Enums', 'Events', 'Listeners', 'Policies', 'Jobs', 'Notifications', 'Support', 'Casts', 'Exceptions', 'Console', 'Http'] as $legacy) {
            $this->assertDirectoryDoesNotExist("{$app}/{$legacy}", "app/{$legacy} should live inside a module");
        }
    }
}
