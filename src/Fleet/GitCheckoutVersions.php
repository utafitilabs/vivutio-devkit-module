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

use Symfony\Component\Process\Process;

/**
 * Reads the branch a sibling checkout has out, as git reports it.
 */
final readonly class GitCheckoutVersions implements CheckoutVersionsInterface
{
    public function versionOf(string $checkout): string
    {
        if (!is_dir($checkout.'/.git')) {
            throw new \RuntimeException(\sprintf('Head mode reads %s as a git repository, and there is none there.', $checkout));
        }

        $branch = (new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $checkout))->mustRun()->getOutput();

        return HeadVersion::fromBranch($branch);
    }
}
