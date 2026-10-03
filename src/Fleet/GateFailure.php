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
 * The first step that did not pass, with what it printed and why it failed.
 */
final class GateFailure extends \RuntimeException
{
    public function __construct(
        public readonly GateStep $step,
        public readonly string $output,
        string $why,
    ) {
        parent::__construct($why);
    }
}
