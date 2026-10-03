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
 * One step of the gate, as the plan states it and the report prints it.
 */
final readonly class GateStep
{
    /**
     * @param list<string> $command      what a shell step runs
     * @param string|null  $subject      what the step acts on: a database url, a route name
     * @param string|null  $expect       text the output must carry
     * @param bool         $allowFailure a non-zero exit is judged by $expect alone
     * @param bool         $inProject    run in the project, or beside it
     */
    public function __construct(
        public GateStepKind $kind,
        public string $label,
        public array $command = [],
        public ?string $subject = null,
        public ?string $expect = null,
        public bool $allowFailure = false,
        public bool $inProject = true,
    ) {
    }

    public function describe(): string
    {
        return [] === $this->command ? $this->label : $this->label.'   $ '.implode(' ', $this->command);
    }
}
