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
 * Where the gate takes the platform from: the published repositories, as the
 * skeleton's install guide does, after a tag; the sibling checkouts, each at
 * the branch it has out, before one.
 */
enum GateMode: string
{
    case Released = 'released';
    case Head = 'head';
}
