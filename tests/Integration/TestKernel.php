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

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Vivutio\Devkit\VivutioDevkitBundle;

/**
 * The smallest installation the development kit runs in.
 */
final class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new VivutioDevkitBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true]);
    }
}
