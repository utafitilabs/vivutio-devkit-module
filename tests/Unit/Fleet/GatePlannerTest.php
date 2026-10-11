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
use Vivutio\Devkit\Fleet\CheckoutVersionsInterface;
use Vivutio\Devkit\Fleet\FleetSettings;
use Vivutio\Devkit\Fleet\GateMode;
use Vivutio\Devkit\Fleet\GatePlan;
use Vivutio\Devkit\Fleet\GatePlanner;
use Vivutio\Devkit\Fleet\GateRequest;
use Vivutio\Devkit\Fleet\GateStep;
use Vivutio\Devkit\Fleet\GateStepKind;

/**
 * The plan is the skeleton's install guide, step by step: from the published
 * repositories after a tag, from the sibling checkouts before one.
 */
final class GatePlannerTest extends TestCase
{
    public function testReleasedModeIsTheInstallGuideAsWritten(): void
    {
        $plan = $this->plan(GateMode::Released);

        self::assertSame(
            ['composer', 'create-project', 'vivutio/skeleton', '/tmp/gate', '--no-interaction', '--no-progress'],
            $this->step($plan, 'create the project')->command,
        );
        self::assertNull($this->find($plan, 'the skeleton validates'), 'released mode has no checkout to validate');
    }

    /**
     * Before a tag the core is not published, so the project is created from
     * the skeleton's checkout without installing, then pointed at the core's
     * checkout and installed from the branch it has out.
     */
    public function testHeadModeBuildsFromTheCheckouts(): void
    {
        $plan = $this->plan(GateMode::Head);

        self::assertSame(GateStepKind::ValidateSkeleton, $this->step($plan, 'the skeleton validates')->kind);
        $create = $this->step($plan, 'create the project from the skeleton checkout')->command;
        self::assertContains('--no-install', $create);
        self::assertContains('--stability=dev', $create);
        self::assertContains('--repository={"type":"vcs","url":"/work/vivutio-skeleton"}', $create);

        self::assertSame(['composer', 'config', 'repositories.vivutio-vivutio', 'vcs', '/work/vivutio'], $this->step($plan, 'vivutio/vivutio: from /work/vivutio')->command);
        self::assertSame(['composer', 'require', 'vivutio/vivutio:0.1.x-dev', '--no-interaction', '--no-progress'], $this->step($plan, 'vivutio/vivutio: require the branch it has out')->command);
    }

    public function testTheInstallationIsMigratedCheckedAndGivenItsFirstAdministrator(): void
    {
        $labels = $this->labels($this->plan(GateMode::Head));

        $order = ['a fresh database for DATABASE_URL', 'the project is pointed at it (.env.local)', 'the core: doctrine:migrations:migrate', 'the core: the mapping is valid', 'the core: the schema is in sync', 'the first administrator', 'the project is served', 'the administrator signs in'];
        $positions = array_map(static fn (string $label): int|false => array_search($label, $labels, true), $order);

        self::assertNotContains(false, $positions, implode("\n", $labels));
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions, 'in the install guide\'s order');
    }

    /** The core's own pages answer the administrator, by route name, never by a path written here. */
    public function testTheCoresPagesAreOpenedAfterSigningIn(): void
    {
        $plan = $this->plan(GateMode::Head);

        self::assertSame('shell_dashboard', $this->step($plan, 'the dashboard answers')->subject);
        self::assertSame('identity_team', $this->step($plan, 'the team answers')->subject);
    }

    /** A module is installed, migrated, checked, and its first page opened, each in turn. */
    public function testEachModuleIsInstalledAndOpened(): void
    {
        $settings = FleetSettings::fromArray([
            'official_modules' => ['property'],
            'modules' => ['property' => ['page' => 'property_index']],
        ]);
        $plan = $this->plan(GateMode::Head, $settings, $settings->resolve());

        self::assertSame(['composer', 'config', 'repositories.vivutio-property-module', 'vcs', '/work/vivutio-property-module'], $this->step($plan, 'vivutio/property-module: from /work/vivutio-property-module')->command);
        self::assertNotNull($this->find($plan, 'vivutio/property-module: doctrine:migrations:migrate'));
        self::assertNotNull($this->find($plan, 'vivutio/property-module: the project\'s own tests'));
        self::assertSame('property_index', $this->step($plan, 'vivutio/property-module: its first page answers')->subject);
    }

    /**
     * The README tells a supplier to type "composer require supplier", so the
     * released gate installs a pack's modules that way, once, and checks the
     * pack was unpacked into them; a module in no pack is required by name.
     */
    public function testReleasedModeInstallsAPacksModulesThroughItsAlias(): void
    {
        $settings = $this->fleet();
        $plan = $this->plan(GateMode::Released, $settings, $settings->resolve());
        $labels = $this->labels($plan);

        self::assertSame(['composer', 'require', 'operator', '--no-interaction', '--no-progress'], $this->step($plan, 'operator: require, as the README says')->command);
        self::assertSame(['composer', 'require', 'vivutio/front-desk-module', '--no-interaction', '--no-progress'], $this->step($plan, 'vivutio/front-desk-module: require')->command);
        self::assertNull($this->find($plan, 'vivutio/touring-module: require'));
        self::assertNull($this->find($plan, 'vivutio/sourcing-module: require'));
        self::assertCount(1, array_keys($labels, 'operator: require, as the README says', true));

        $unpacked = $this->step($plan, 'operator: unpacked into its modules');
        self::assertSame(GateStepKind::PackUnpacked, $unpacked->kind);
        self::assertSame('vivutio/operator-pack', $unpacked->subject);
        self::assertSame(['vivutio/touring-module', 'vivutio/sourcing-module'], $unpacked->requires);
        self::assertLessThan(array_search('vivutio/touring-module: doctrine:migrations:migrate', $labels, true), array_search('operator: unpacked into its modules', $labels, true));
    }

    /** Before a tag a pack is required from its checkout, after the modules it names. */
    public function testHeadModeRequiresEachPackFromItsCheckout(): void
    {
        $settings = $this->fleet();
        $plan = $this->plan(GateMode::Head, $settings, $settings->resolve());
        $labels = $this->labels($plan);

        self::assertSame(['composer', 'config', 'repositories.vivutio-operator-pack', 'vcs', '/work/vivutio-operator-pack'], $this->step($plan, 'vivutio/operator-pack: from /work/vivutio-operator-pack')->command);
        self::assertSame(['composer', 'require', 'vivutio/operator-pack:0.1.x-dev', '--no-interaction', '--no-progress'], $this->step($plan, 'vivutio/operator-pack: require the branch it has out')->command);
        self::assertSame(['vivutio/touring-module', 'vivutio/sourcing-module'], $this->step($plan, 'operator: unpacked into its modules')->requires);
        self::assertGreaterThan(array_search('vivutio/sourcing-module: its first page answers', $labels, true), array_search('vivutio/operator-pack: from /work/vivutio-operator-pack', $labels, true));
    }

    /** A pack the run does not install all of is not required: it would bring modules the run left out. */
    public function testAPackIsLeftOutWhenTheRunLeavesOutOneOfItsModules(): void
    {
        $settings = $this->fleet();
        $plan = $this->plan(GateMode::Released, $settings, $settings->resolve(['touring']));

        self::assertNull($this->find($plan, 'operator: require, as the README says'));
        self::assertNotNull($this->find($plan, 'vivutio/touring-module: require'));
    }

    /** The README check is told every module package and every pack's command. */
    public function testTheReadmeIsCheckedForEveryModuleAndEveryPack(): void
    {
        $settings = $this->fleet();
        $plan = $this->plan(GateMode::Head, $settings, $settings->resolve());

        self::assertSame(
            ['vivutio/front-desk-module', 'vivutio/touring-module', 'vivutio/sourcing-module', 'composer require operator'],
            $this->step($plan, 'the skeleton lists every official module')->requires,
        );
    }

    private function fleet(): FleetSettings
    {
        return FleetSettings::fromArray([
            'official_modules' => ['front-desk', 'touring', 'sourcing'],
            'modules' => [
                'front-desk' => ['page' => 'front_desk_places'],
                'touring' => ['page' => 'touring_tours'],
                'sourcing' => ['page' => 'sourcing_requests'],
            ],
            'packs' => ['operator' => ['touring', 'sourcing']],
        ]);
    }

    /**
     * @param list<\Vivutio\Devkit\Fleet\ModuleUnderGate> $modules
     */
    private function plan(GateMode $mode, ?FleetSettings $settings = null, array $modules = []): GatePlan
    {
        $versions = new class implements CheckoutVersionsInterface {
            public function versionOf(string $checkout): string
            {
                return '0.1.x-dev';
            }
        };

        return (new GatePlanner($versions))->plan(
            $settings ?? FleetSettings::fromArray([]),
            new GateRequest($mode, '/tmp/gate', '/work', $modules, ['DATABASE_URL' => 'postgresql://app:app@127.0.0.1:5434/fleet_gate']),
        );
    }

    private function find(GatePlan $plan, string $label): ?GateStep
    {
        foreach ($plan->steps as $step) {
            if ($label === $step->label) {
                return $step;
            }
        }

        return null;
    }

    private function step(GatePlan $plan, string $label): GateStep
    {
        return $this->find($plan, $label) ?? self::fail(\sprintf("No step \"%s\" in:\n%s", $label, implode("\n", $this->labels($plan))));
    }

    /**
     * @return list<string>
     */
    private function labels(GatePlan $plan): array
    {
        return array_map(static fn (GateStep $step): string => $step->label, $plan->steps);
    }
}
