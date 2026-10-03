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
 * One run of the gate: the mode, where the project is created, where the
 * sibling checkouts are, the modules to install and the databases it owns.
 */
final readonly class GateRequest
{
    /**
     * @param list<ModuleUnderGate> $modules   the modules to install, in order
     * @param array<string, string> $databases the databases this run owns, by the variable the project reads each
     *                                         through; every one is DROPPED and recreated, so none may be a
     *                                         database anybody else uses
     */
    public function __construct(
        public GateMode $mode,
        public string $project,
        public string $workspace,
        public array $modules,
        public array $databases,
        public bool $keep = false,
    ) {
    }

    public function isHead(): bool
    {
        return GateMode::Head === $this->mode;
    }

    /** The skeleton's checkout, which head mode creates the project from. */
    public function skeletonCheckout(): string
    {
        return rtrim($this->workspace, '/').'/vivutio-skeleton';
    }

    /** The core's checkout, which head mode installs the core from. */
    public function coreCheckout(): string
    {
        return rtrim($this->workspace, '/').'/vivutio';
    }
}
