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
 * The skeleton's install guide, as a list of steps the runner performs.
 *
 * Released mode is the guide as written, against the published repositories.
 * Head mode is the same guide against the sibling checkouts: the project is
 * created from the skeleton's checkout without installing, since the core it
 * requires is not published before its first tag, then pointed at the core's
 * checkout and installed from the branch it has out.
 */
final readonly class GatePlanner
{
    public const string ADMIN_EMAIL = 'gate@example.test';
    public const string ADMIN_PASSWORD = 'fleet-gate-passphrase';
    public const string ADMIN_FIRST_NAME = 'Ada';
    public const string ADMIN_LAST_NAME = 'Mwangi';

    /** The variable the created project reads its database through. */
    public const string DATABASE_ENV = 'DATABASE_URL';

    /** The core's pages the administrator opens, by route name, with what each must show. */
    public const array CORE_PAGES = [
        'the dashboard' => ['shell_dashboard', 'data-dashboard="organization"'],
        'the team' => ['identity_team', self::ADMIN_EMAIL],
    ];

    public function __construct(private CheckoutVersionsInterface $versions)
    {
    }

    public function plan(FleetSettings $settings, GateRequest $request): GatePlan
    {
        $steps = [];

        if ($request->isHead()) {
            // https://getcomposer.org/doc/03-cli.md#validate
            $steps[] = new GateStep(GateStepKind::ValidateSkeleton, 'the skeleton validates', ['composer', 'validate', '--strict', '--no-check-publish'], subject: $request->skeletonCheckout());
            $steps[] = new GateStep(GateStepKind::ReadmeListsModules, 'the skeleton lists every official module', subject: $request->skeletonCheckout());
        }

        $steps = [...$steps, ...$this->createTheProject($request)];

        foreach ($request->databases as $variable => $url) {
            $steps[] = new GateStep(GateStepKind::FreshDatabase, 'a fresh database for '.$variable, subject: $url);
        }
        $steps[] = new GateStep(GateStepKind::WriteEnvironment, 'the project is pointed at it (.env.local)');

        $steps = [...$steps, ...$this->install($settings, 'the core')];

        $steps[] = new GateStep(
            GateStepKind::Shell,
            'the first administrator',
            ['php', 'bin/console', 'identity:user:create', self::ADMIN_EMAIL, self::ADMIN_FIRST_NAME, self::ADMIN_LAST_NAME, '--tier=super-admin', '--password='.self::ADMIN_PASSWORD, '--no-interaction'],
            expect: self::ADMIN_EMAIL,
        );
        $steps[] = new GateStep(GateStepKind::Serve, 'the project is served');
        $steps[] = new GateStep(GateStepKind::SignIn, 'the administrator signs in');
        foreach (self::CORE_PAGES as $page => [$route, $shows]) {
            $steps[] = new GateStep(GateStepKind::OpenPage, $page.' answers', subject: $route, expect: $shows);
        }

        foreach ($request->modules as $module) {
            $steps = [...$steps, ...$this->addTheModule($settings, $request, $module)];
        }

        return new GatePlan($steps);
    }

    /**
     * @return list<GateStep>
     */
    private function createTheProject(GateRequest $request): array
    {
        $command = ['composer', 'create-project', 'vivutio/skeleton', $request->project, '--no-interaction', '--no-progress'];

        if (!$request->isHead()) {
            return [new GateStep(GateStepKind::Shell, 'create the project', $command, inProject: false)];
        }

        $command = [...$command, '--no-install', '--stability=dev', '--repository='.json_encode(['type' => 'vcs', 'url' => $request->skeletonCheckout()], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES)];

        return [
            new GateStep(GateStepKind::Shell, 'create the project from the skeleton checkout', $command, inProject: false),
            ...$this->pointAt('vivutio/vivutio', $request->coreCheckout()),
        ];
    }

    /**
     * The install guide's migrate and check, run again after every module,
     * since a module adds tables of its own.
     *
     * @return list<GateStep>
     */
    private function install(FleetSettings $settings, string $what): array
    {
        $steps = [new GateStep(GateStepKind::Shell, $what.': cache:clear --no-warmup', ['php', 'bin/console', 'cache:clear', '--no-warmup'])];

        foreach ($settings->afterMigrate as $command) {
            $steps[] = new GateStep(GateStepKind::Shell, $what.': '.$command, ['php', 'bin/console', $command, '--no-interaction']);
        }

        $steps[] = new GateStep(GateStepKind::Shell, $what.': assets:install', ['php', 'bin/console', 'assets:install', 'public']);
        // The shipped migrations and the shipped entities must agree: a package
        // whose entity moved on without its migration is caught here.
        $steps[] = new GateStep(GateStepKind::Shell, $what.': the mapping is valid', ['php', 'bin/console', 'doctrine:schema:validate', '--skip-sync', '--no-interaction']);
        $steps[] = new GateStep(GateStepKind::Shell, $what.': the schema is in sync', ['php', 'bin/console', 'doctrine:schema:validate', '--skip-mapping', '--no-interaction'], expect: 'in sync', allowFailure: true);

        return $steps;
    }

    /**
     * @return list<GateStep>
     */
    private function addTheModule(FleetSettings $settings, GateRequest $request, ModuleUnderGate $module): array
    {
        $package = $module->package();

        $steps = $request->isHead()
            ? $this->pointAt($package, $module->checkout($request->workspace))
            : [new GateStep(GateStepKind::Shell, $package.': require', ['composer', 'require', $package, '--no-interaction', '--no-progress'])];

        return [
            ...$steps,
            ...$this->install($settings, $package),
            new GateStep(GateStepKind::Shell, $package.': the project\'s own tests', ['composer', 'test']),
            new GateStep(GateStepKind::Serve, $package.': the project is served again'),
            new GateStep(GateStepKind::SignIn, $package.': the administrator signs in'),
            new GateStep(GateStepKind::OpenPage, $package.': its first page answers', subject: $module->page),
        ];
    }

    /**
     * A sibling checkout standing in for a published repository, and the
     * require that names the branch it has out.
     *
     * @return list<GateStep>
     */
    private function pointAt(string $package, string $checkout): array
    {
        return [
            new GateStep(GateStepKind::Shell, $package.': from '.$checkout, ['composer', 'config', 'repositories.'.str_replace('/', '-', $package), 'vcs', $checkout]),
            new GateStep(GateStepKind::Shell, $package.': require the branch it has out', ['composer', 'require', $package.':'.$this->versions->versionOf($checkout), '--no-interaction', '--no-progress']),
        ];
    }
}
