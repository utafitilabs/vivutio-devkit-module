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

namespace Vivutio\Devkit;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The development kit. It installs through require-dev, which is the
 * production firewall: a command that creates projects, drops databases and
 * starts servers is never in an installation's production container.
 */
final class VivutioDevkitBundle extends AbstractBundle
{
    /** Configuration lives under "devkit:", not the class-derived "vivutio_devkit:". */
    protected string $extensionAlias = 'devkit';

    /**
     * What the fleet is made of: the official modules in install order, the
     * commands every install is followed by, and each module's first page.
     *
     * @see https://symfony.com/doc/current/bundles/configuration.html#using-the-abstractbundle-class
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('fleet')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('official_modules')->scalarPrototype()->end()->end()
                        ->arrayNode('after_migrate')->scalarPrototype()->end()->end()
                        ->arrayNode('modules')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()->scalarNode('page')->isRequired()->end()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('devkit.fleet', \is_array($config['fleet'] ?? null) ? $config['fleet'] : []);
        $container->import('../config/services.php');
    }
}
