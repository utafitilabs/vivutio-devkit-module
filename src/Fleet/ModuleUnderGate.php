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
 * An official module, as the gate installs it: its name, and the route of the
 * first page it answers once installed.
 */
final readonly class ModuleUnderGate
{
    public function __construct(
        public string $name,
        public string $page,
    ) {
    }

    public function package(): string
    {
        return 'vivutio/'.$this->name.'-module';
    }

    public function checkout(string $workspace): string
    {
        return rtrim($workspace, '/').'/vivutio-'.$this->name.'-module';
    }
}
