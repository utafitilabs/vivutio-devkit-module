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

namespace Vivutio\Devkit\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vivutio\Devkit\Fleet\FleetSettings;
use Vivutio\Devkit\Fleet\GateFailure;
use Vivutio\Devkit\Fleet\GateMode;
use Vivutio\Devkit\Fleet\GatePlanner;
use Vivutio\Devkit\Fleet\GateRequest;
use Vivutio\Devkit\Fleet\GateRunner;
use Vivutio\Devkit\Fleet\GateStep;
use Vivutio\Devkit\Fleet\ModuleUnderGate;

/**
 * The fleet gate: a project created from the skeleton in an empty directory,
 * the core and every official module installed into it, and the administrator
 * signed in and answered by every page. Before a tag in head mode, from the
 * sibling checkouts; after one, from the published repositories.
 */
#[AsCommand(
    name: 'fleet:gate',
    description: 'Create a project from the skeleton and install the platform into it, proving it installs and runs as one product (development only).',
)]
final class FleetGateCommand extends Command
{
    private const string DEFAULT_DATABASE_URL = 'postgresql://app:app@127.0.0.1:5434/fleet_gate?serverVersion=17&charset=utf8';

    public function __construct(
        private readonly FleetSettings $settings,
        private readonly GatePlanner $planner,
        private readonly GateRunner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'released (the published repositories) or head (the sibling checkouts)', GateMode::Released->value)
            ->addOption('workspace', null, InputOption::VALUE_REQUIRED, 'Head mode: the directory holding the sibling checkouts', self::defaultWorkspace())
            ->addOption('module', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A module to install instead of the official list; repeatable')
            ->addOption('database-url', null, InputOption::VALUE_REQUIRED, 'The gate\'s own database. IT IS DROPPED AND RECREATED', self::environment('FLEET_GATE_DATABASE_URL') ?? self::DEFAULT_DATABASE_URL)
            ->addOption('keep', null, InputOption::VALUE_NONE, 'Keep the created project afterwards, for a look')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the plan and run none of it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $asked = self::text($input, 'mode');
        $mode = GateMode::tryFrom($asked);
        if (null === $mode) {
            $io->error(\sprintf('The mode is "released" or "head", not "%s".', $asked));

            return Command::INVALID;
        }

        try {
            /** @var list<string> $only */
            $only = $input->getOption('module');
            $request = new GateRequest(
                $mode,
                rtrim(sys_get_temp_dir(), '/').'/fleet-gate-'.bin2hex(random_bytes(4)),
                self::text($input, 'workspace'),
                $this->settings->resolve($only),
                [GatePlanner::DATABASE_ENV => self::text($input, 'database-url')],
                true === $input->getOption('keep'),
            );
            $plan = $this->planner->plan($this->settings, $request);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->title(\sprintf('Fleet gate · %s mode · %d steps', $mode->value, $plan->count()));
        $io->text([
            'project    '.$request->project,
            'workspace  '.($request->isHead() ? $request->workspace : 'none: released mode reads the published repositories'),
            'modules    '.([] === $request->modules ? 'none yet' : implode(', ', array_map(static fn (ModuleUnderGate $m): string => $m->package(), $request->modules))),
            'database   '.GatePlanner::DATABASE_ENV.' (dropped and recreated)',
        ]);
        $io->newLine();

        if (true === $input->getOption('dry-run')) {
            $io->section('The plan');
            foreach ($plan->describe() as $i => $line) {
                $io->writeln(\sprintf(' %2d. %s', $i + 1, $line));
            }
            $io->newLine();
            $io->note('Dry run: nothing was created, dropped or served.');

            return Command::SUCCESS;
        }

        try {
            $this->runner->run($plan, $request, static function (GateStep $step, bool $passed, string $out) use ($io): void {
                $io->writeln(($passed ? ' <info>✓</info> ' : ' <error>✗</error> ').$step->label);
                if (!$passed) {
                    $io->newLine();
                    $io->writeln($out);
                }
            });
        } catch (GateFailure $failure) {
            $io->newLine();
            $io->error(['FLEET GATE RED at: '.$failure->step->label, $failure->getMessage(), [] === $failure->step->command ? '' : '$ '.implode(' ', $failure->step->command)]);
            if ($request->keep) {
                $io->note('kept '.$request->project);
            }
            $this->runner->tearDown($request);

            return Command::FAILURE;
        }

        $this->runner->tearDown($request);
        $io->success(\sprintf('The platform installs and runs as one product (%s mode).', $mode->value));
        if ($request->keep) {
            $io->note('kept '.$request->project);
        }

        return Command::SUCCESS;
    }

    /** The directory holding this checkout of the kit, where its siblings are. */
    private static function defaultWorkspace(): string
    {
        $kit = realpath(\dirname(__DIR__, 2));

        return false === $kit ? '' : \dirname($kit);
    }

    private static function text(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return \is_string($value) ? $value : '';
    }

    private static function environment(string $name): ?string
    {
        $value = getenv($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
