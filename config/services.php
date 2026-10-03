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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Vivutio\Devkit\Command\FleetGateCommand;
use Vivutio\Devkit\Fleet\FleetSettings;
use Vivutio\Devkit\Fleet\GatePlanner;
use Vivutio\Devkit\Fleet\GateRunner;
use Vivutio\Devkit\Fleet\GitCheckoutVersions;

/*
 * Every service defined explicitly, with an id prefixed by the bundle's alias;
 * nothing is autowired or autoconfigured, so the command's tag is applied by
 * hand.
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('devkit.fleet.settings', FleetSettings::class)
        ->factory([FleetSettings::class, 'fromArray'])
        ->args([param('devkit.fleet')]);

    $services->set('devkit.fleet.versions', GitCheckoutVersions::class);
    $services->set('devkit.fleet.planner', GatePlanner::class)
        ->args([service('devkit.fleet.versions')]);
    $services->set('devkit.fleet.runner', GateRunner::class);

    /*
     * A bare tag: the console reads the name from #[AsCommand] and registers
     * the command lazily, autoconfigured or not.
     *
     * @see vendor/symfony/console/DependencyInjection/AddConsoleCommandPass.php
     */
    $services->set('devkit.command.fleet_gate', FleetGateCommand::class)
        ->args([service('devkit.fleet.settings'), service('devkit.fleet.planner'), service('devkit.fleet.runner')])
        ->tag('console.command');
};
