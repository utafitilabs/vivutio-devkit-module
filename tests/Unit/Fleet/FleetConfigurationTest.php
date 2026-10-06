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
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Vivutio\Devkit\VivutioDevkitBundle;

/**
 * A module is named as its package is, "front-desk" for
 * vivutio/front-desk-module, and the configuration keeps the name as written:
 * Symfony turns a dash in a key into an underscore unless told not to.
 *
 * @see https://symfony.com/doc/current/components/config/definition.html#normalization
 */
final class FleetConfigurationTest extends TestCase
{
    public function testAModulesNameIsKeptAsItsPackageHasIt(): void
    {
        $extension = (new VivutioDevkitBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        $config = (new Processor())->processConfiguration($configuration, [['fleet' => [
            'official_modules' => ['property', 'front-desk'],
            'modules' => ['property' => ['page' => 'property_list'], 'front-desk' => ['page' => 'front_desk_places']],
        ]]]);

        $fleet = $config['fleet'];
        self::assertIsArray($fleet);
        self::assertIsArray($fleet['modules']);
        self::assertSame(['property', 'front-desk'], array_keys($fleet['modules']));
    }
}
