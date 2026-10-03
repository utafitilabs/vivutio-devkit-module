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

namespace Vivutio\Devkit\Tests\Unit\Fleet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vivutio\Devkit\Fleet\HeadVersion;

/**
 * The version composer gives the branch a checkout has out: a version line is
 * its dev series, any other branch its own dev version.
 */
final class HeadVersionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function branches(): iterable
    {
        yield 'a version line' => ['0.1', '0.1.x-dev'];
        yield 'a later line' => ['1.12', '1.12.x-dev'];
        yield 'a feature branch' => ['sign-in', 'dev-sign-in'];
        yield 'with a trailing newline, as git prints it' => ["0.1\n", '0.1.x-dev'];
    }

    #[DataProvider('branches')]
    public function testABranchIsTheVersionComposerGivesIt(string $branch, string $version): void
    {
        self::assertSame($version, HeadVersion::fromBranch($branch));
    }

    public function testADetachedHeadIsRefused(): void
    {
        $this->expectExceptionMessage('detached HEAD');

        HeadVersion::fromBranch('HEAD');
    }
}
