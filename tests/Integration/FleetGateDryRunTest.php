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

namespace Vivutio\Devkit\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command, from an installation that has the development kit: a dry run
 * prints the plan and runs none of it.
 */
final class FleetGateDryRunTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testADryRunPrintsThePlanAndRunsNoneOfIt(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('fleet:gate'));

        $tester->execute(['--mode' => 'released', '--dry-run' => true]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('composer create-project vivutio/skeleton', $display);
        self::assertStringContainsString('the first administrator', $display);
        self::assertStringContainsString('Dry run: nothing was created, dropped or served.', $display);
    }

    public function testAModeThatIsNeitherIsRefused(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('fleet:gate'));

        $tester->execute(['--mode' => 'sideways', '--dry-run' => true]);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString('"released" or "head"', $tester->getDisplay());
    }
}
