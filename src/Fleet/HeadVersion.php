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
 * The version composer gives the branch a checkout has out. A version line,
 * `0.1`, is its dev series, `0.1.x-dev`; any other branch is `dev-<name>`.
 *
 * @see https://getcomposer.org/doc/articles/versions.md#branches
 */
final class HeadVersion
{
    public static function fromBranch(string $branch): string
    {
        $branch = trim($branch);
        if ('' === $branch || 'HEAD' === $branch) {
            throw new \InvalidArgumentException('That checkout is on a detached HEAD; check out a branch before gating it.');
        }

        return 1 === preg_match('/^\d+\.\d+$/', $branch) ? $branch.'.x-dev' : 'dev-'.$branch;
    }
}
