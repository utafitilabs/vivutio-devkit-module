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

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

/**
 * Performs a plan, step by step, and stops at the first that does not pass.
 */
final class GateRunner
{
    private ?Process $server = null;
    private string $baseUrl = '';
    private ?HttpBrowser $session = null;

    /**
     * @param \Closure(GateStep, bool, string): void $report called once per step, before the next one starts
     *
     * @throws GateFailure on the first step that does not pass
     */
    public function run(GatePlan $plan, GateRequest $request, \Closure $report): void
    {
        try {
            foreach ($plan->steps as $step) {
                try {
                    $output = $this->perform($step, $request);
                } catch (GateFailure $failure) {
                    throw $failure;
                } catch (\Throwable $e) {
                    // A step that throws is a step that failed: reported as
                    // one, so the run still names where it stopped and tears
                    // down what it made.
                    throw new GateFailure($step, $e::class.': '.$e->getMessage(), 'the step threw');
                }
                $report($step, true, $output);
            }
        } catch (GateFailure $failure) {
            $report($failure->step, false, $failure->output);

            throw $failure;
        } finally {
            $this->server?->stop();
            $this->server = null;
        }
    }

    /** Removes the created project, unless it is to be kept for a look. */
    public function tearDown(GateRequest $request): void
    {
        $this->server?->stop();
        $this->server = null;

        if (!$request->keep && is_dir($request->project)) {
            (new Process(['rm', '-rf', $request->project]))->mustRun();
        }
    }

    /**
     * The child's environment, scrubbed of the parent's.
     *
     * The gate is a command of one installation that runs the console,
     * composer and phpunit of another, and a child is handed the parent's
     * variables unless told otherwise. The parent booted through Dotenv, so it
     * carries its own .env values and SYMFONY_DOTENV_VARS, the marker naming
     * them, and that marker is not inert in the child: Dotenv overwrites a
     * variable it names even where the child already has a value, so phpunit's
     * forced APP_ENV=test would be put back to the project's dev, and its own
     * tests would not run as tests.
     *
     * So every variable the marker names is removed, the marker with them, and
     * the variables a launching context sets: APP_DEBUG, KERNEL_CLASS and
     * SHELL_VERBOSITY. The child is given only the gate's own: the databases
     * this run owns, and composer's memory. A value of false is how a process
     * has a variable removed.
     *
     * @see https://symfony.com/doc/current/components/process.html#setting-environment-variables-for-processes
     * @see vendor/symfony/process/Process.php — start(), getDefaultEnv()
     * @see vendor/symfony/dotenv/Dotenv.php — populate(), the marker
     *
     * @return array<string, string|false>
     */
    public function childEnvironment(GateRequest $request): array
    {
        $scrubbed = ['SYMFONY_DOTENV_VARS' => false, 'APP_DEBUG' => false, 'KERNEL_CLASS' => false, 'SHELL_VERBOSITY' => false];

        $marker = $_SERVER['SYMFONY_DOTENV_VARS'] ?? $_ENV['SYMFONY_DOTENV_VARS'] ?? '';
        foreach (explode(',', \is_string($marker) ? $marker : '') as $name) {
            if ('' !== $name) {
                $scrubbed[$name] = false;
            }
        }

        return [...$scrubbed, ...$request->databases, 'COMPOSER_MEMORY_LIMIT' => '-1'];
    }

    private function perform(GateStep $step, GateRequest $request): string
    {
        return match ($step->kind) {
            GateStepKind::Shell => $this->shell($step, $request, $step->inProject ? $request->project : \dirname($request->project)),
            GateStepKind::ValidateSkeleton => $this->shell($step, $request, $request->skeletonCheckout()),
            GateStepKind::ReadmeListsModules => $this->readmeListsModules($step, $request),
            GateStepKind::FreshDatabase => $this->freshDatabase($step),
            GateStepKind::WriteEnvironment => $this->writeEnvironment($request),
            GateStepKind::Serve => $this->serve($step, $request),
            GateStepKind::SignIn => $this->signIn($step, $request),
            GateStepKind::OpenPage => $this->openPage($step, $request),
        };
    }

    private function shell(GateStep $step, GateRequest $request, string $cwd): string
    {
        $process = new Process($step->command, $cwd, $this->childEnvironment($request), timeout: 900);
        $process->run();
        $output = $process->getOutput().$process->getErrorOutput();

        if (!$process->isSuccessful() && !$step->allowFailure) {
            throw new GateFailure($step, $output, \sprintf('the command exited %d', (int) $process->getExitCode()));
        }

        if (null !== $step->expect && !str_contains($output, $step->expect)) {
            throw new GateFailure($step, $output, \sprintf('the output does not carry "%s"', $step->expect));
        }

        return $output;
    }

    /**
     * The skeleton's README lists every official module the gate installs: the
     * two lists drift silently otherwise, and a module the gate proves is one
     * nobody is told to install.
     */
    private function readmeListsModules(GateStep $step, GateRequest $request): string
    {
        $readme = (string) @file_get_contents($request->skeletonCheckout().'/README.md');

        $missing = [];
        foreach ($request->modules as $module) {
            if (!str_contains($readme, $module->package())) {
                $missing[] = $module->package();
            }
        }

        if ([] !== $missing) {
            throw new GateFailure($step, implode("\n", $missing), 'the skeleton\'s README does not list these official modules');
        }

        return [] === $request->modules ? 'no official module yet' : 'all listed';
    }

    /** The database the url names is DROPPED and recreated. */
    private function freshDatabase(GateStep $step): string
    {
        $url = (string) $step->subject;
        $parts = parse_url($url);
        $name = \is_array($parts) ? ltrim($parts['path'] ?? '', '/') : '';
        if (!\is_array($parts) || '' === $name) {
            throw new GateFailure($step, $url, 'the url names no database');
        }

        try {
            $pdo = new \PDO(\sprintf('pgsql:host=%s;port=%d;dbname=postgres', $parts['host'] ?? '127.0.0.1', $parts['port'] ?? 5432), $parts['user'] ?? null, $parts['pass'] ?? null);
            $pdo->exec(\sprintf('DROP DATABASE IF EXISTS "%s"', $name));
            $pdo->exec(\sprintf('CREATE DATABASE "%s"', $name));
        } catch (\PDOException $e) {
            throw new GateFailure($step, $e->getMessage(), 'the database server would not answer');
        }

        return $name.' dropped and recreated';
    }

    private function writeEnvironment(GateRequest $request): string
    {
        $lines = '';
        foreach ($request->databases as $variable => $url) {
            $lines .= $variable.'="'.$url.'"'."\n";
        }
        file_put_contents($request->project.'/.env.local', $lines);

        return $lines;
    }

    /**
     * Served with PHP's built-in server and the same scrubbed environment as
     * every other child. Nobody reads its output, so it has none: a process
     * fetches a child's output into pipes, the dev server writes lines on every
     * request, and a full pipe blocks the server until the next request times
     * out. Its timeout is lifted, since it outlives every install.
     *
     * @see https://symfony.com/doc/current/components/process.html#disabling-output
     */
    private function serve(GateStep $step, GateRequest $request): string
    {
        $this->server?->stop();

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if (false === $socket) {
            throw new GateFailure($step, '', 'no free port to serve on');
        }
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        $this->baseUrl = 'http://'.$address;
        $server = new Process(['php', '-S', $address, '-t', 'public'], $request->project, $this->childEnvironment($request));
        $server->setTimeout(null);
        $server->disableOutput();
        $server->start();
        $this->server = $server;

        $deadline = microtime(true) + 15;
        do {
            usleep(200_000);
            $up = @file_get_contents($this->baseUrl.'/login', false, stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]));
        } while (false === $up && microtime(true) < $deadline);

        if (false === $up) {
            throw new GateFailure($step, '', 'the built-in server did not answer within 15 s');
        }

        return $this->baseUrl;
    }

    /** Through the sign-in form itself, as a person signs in. */
    private function signIn(GateStep $step, GateRequest $request): string
    {
        $browser = new HttpBrowser(HttpClient::create());

        $page = $browser->request('GET', $this->baseUrl.$this->routePath($step, $request, 'identity_login'));
        $this->expectStatus($step, $browser, 'the sign-in page answers');

        $browser->submit($page->selectButton('Sign in')->form([
            '_username' => GatePlanner::ADMIN_EMAIL,
            '_password' => GatePlanner::ADMIN_PASSWORD,
        ]));
        $this->expectStatus($step, $browser, 'signing in lands on a page');

        $body = (string) $browser->getResponse()->getContent();
        if (str_contains($body, 'name="_password"')) {
            throw new GateFailure($step, $body, 'the sign-in form is still there after signing in');
        }

        $this->session = $browser;

        return 'signed in as '.GatePlanner::ADMIN_EMAIL;
    }

    private function openPage(GateStep $step, GateRequest $request): string
    {
        $browser = $this->session ?? throw new GateFailure($step, '', 'nobody has signed in');

        $path = $this->routePath($step, $request, (string) $step->subject);
        $browser->request('GET', $this->baseUrl.$path);
        $this->expectStatus($step, $browser, $path.' answers');

        $body = (string) $browser->getResponse()->getContent();
        if (null !== $step->expect && !str_contains($body, $step->expect)) {
            throw new GateFailure($step, $body, \sprintf('%s does not show "%s"', $path, $step->expect));
        }

        return $path;
    }

    /**
     * A page's path, asked of the created project's router by route name: the
     * name is the contract the gate holds the project to, the path is the
     * project's answer, so a page that moves breaks nothing here.
     *
     * @see https://symfony.com/doc/current/routing.html#debugging-routes
     */
    private function routePath(GateStep $step, GateRequest $request, string $name): string
    {
        $process = new Process(['php', 'bin/console', 'debug:router', $name, '--format=json', '--no-interaction'], $request->project, $this->childEnvironment($request), timeout: 60);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new GateFailure($step, trim($process->getErrorOutput().$process->getOutput()), \sprintf('the project has no route named "%s"', $name));
        }

        $route = json_decode($process->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
        $path = \is_array($route) ? ($route['path'] ?? null) : null;

        return \is_string($path) ? $path : throw new GateFailure($step, $process->getOutput(), \sprintf('the route "%s" states no path', $name));
    }

    private function expectStatus(GateStep $step, HttpBrowser $browser, string $what): void
    {
        $status = $browser->getInternalResponse()->getStatusCode();
        if (200 !== $status) {
            throw new GateFailure($step, (string) $browser->getInternalResponse()->getContent(), \sprintf('%s with %d, not 200', $what, $status));
        }
    }
}
