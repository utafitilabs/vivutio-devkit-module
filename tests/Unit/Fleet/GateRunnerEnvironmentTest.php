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

use PHPUnit\Framework\TestCase;
use Vivutio\Devkit\Fleet\GateMode;
use Vivutio\Devkit\Fleet\GateRequest;
use Vivutio\Devkit\Fleet\GateRunner;

/**
 * The created project decides its own environment. The gate runs inside an
 * installation that booted through Dotenv, and a child inherits that unless
 * told otherwise: the marker naming every variable Dotenv set would make the
 * child's own .env overwrite what phpunit forces, and the project's tests
 * would run as dev.
 */
final class GateRunnerEnvironmentTest extends TestCase
{
    private const array DATABASES = ['DATABASE_URL' => 'postgresql://app:app@127.0.0.1:5434/fleet_gate'];

    /** @var array<mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['SYMFONY_DOTENV_VARS'] = 'APP_ENV,APP_SECRET,DATABASE_URL';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testEveryVariableTheParentsDotenvSetIsRemovedWithItsMarker(): void
    {
        $environment = (new GateRunner())->childEnvironment($this->request());

        foreach (['SYMFONY_DOTENV_VARS', 'APP_ENV', 'APP_SECRET'] as $name) {
            self::assertFalse($environment[$name], $name.' is removed for the child');
        }
    }

    public function testWhatALaunchingContextSetsIsRemovedToo(): void
    {
        $environment = (new GateRunner())->childEnvironment($this->request());

        foreach (['APP_DEBUG', 'KERNEL_CLASS', 'SHELL_VERBOSITY'] as $name) {
            self::assertFalse($environment[$name], $name.' is removed for the child');
        }
    }

    /** What the child is given is only the gate's own: the databases it owns. */
    public function testTheChildIsGivenTheDatabasesThisRunOwns(): void
    {
        $environment = (new GateRunner())->childEnvironment($this->request());

        self::assertSame(self::DATABASES['DATABASE_URL'], $environment['DATABASE_URL']);
        self::assertSame('-1', $environment['COMPOSER_MEMORY_LIMIT']);
    }

    private function request(): GateRequest
    {
        return new GateRequest(GateMode::Head, '/tmp/gate', '/work', [], self::DATABASES);
    }
}
