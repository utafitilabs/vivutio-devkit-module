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
 * Every step of one run of the gate, in order.
 */
final readonly class GatePlan
{
    /**
     * @param list<GateStep> $steps
     */
    public function __construct(public array $steps)
    {
    }

    public function count(): int
    {
        return \count($this->steps);
    }

    /**
     * @return list<string>
     */
    public function describe(): array
    {
        return array_map(static fn (GateStep $step): string => $step->describe(), $this->steps);
    }
}
