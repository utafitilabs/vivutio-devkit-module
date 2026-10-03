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
 * The version a sibling checkout is required at in head mode: whatever branch
 * it has out. An interface so the plan can be tested without a checkout.
 */
interface CheckoutVersionsInterface
{
    public function versionOf(string $checkout): string;
}
