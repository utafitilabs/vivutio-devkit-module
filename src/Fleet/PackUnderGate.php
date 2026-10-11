<?php

declare(strict_types=1);

/*
 * This file is part of the vivutio development kit.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vivutio\Devkit\Fleet;

/**
 * A pack, as the gate installs it: its alias, which the recipes endpoint maps
 * to its package, and the modules it brings, by their fleet names.
 */
final readonly class PackUnderGate
{
    /**
     * @param list<string> $modules
     */
    public function __construct(
        public string $name,
        public array $modules,
    ) {
    }

    public function package(): string
    {
        return 'vivutio/'.$this->name.'-pack';
    }

    public function checkout(string $workspace): string
    {
        return rtrim($workspace, '/').'/vivutio-'.$this->name.'-pack';
    }

    /**
     * The packages it unpacks into.
     *
     * @return list<string>
     */
    public function packages(): array
    {
        return array_map(static fn (string $module): string => (new ModuleUnderGate($module, ''))->package(), $this->modules);
    }
}
