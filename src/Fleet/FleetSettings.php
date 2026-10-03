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
 * What the fleet is made of, as configuration: the official modules in the
 * order they install, the commands every install is followed by, and what the
 * gate has to be told about each module. Adding a module is a line of an
 * installation's devkit configuration, never a release of this kit.
 */
final readonly class FleetSettings
{
    /** What every install is followed by when nothing else is configured. */
    public const array AFTER_MIGRATE = ['doctrine:migrations:migrate', 'cache:warmup'];

    /**
     * @param list<string>                   $officialModules in install order: a module after any it builds on
     * @param list<string>                   $afterMigrate    console commands run after every install
     * @param array<string, ModuleUnderGate> $modules         every module the gate knows, by name
     */
    public function __construct(
        public array $officialModules,
        public array $afterMigrate,
        public array $modules,
    ) {
    }

    /**
     * @param array<mixed> $config the bundle's fleet configuration
     */
    public static function fromArray(array $config): self
    {
        $modules = [];
        $configured = \is_array($config['modules'] ?? null) ? $config['modules'] : [];
        foreach ($configured as $name => $module) {
            $name = (string) $name;
            $page = \is_array($module) ? ($module['page'] ?? null) : null;
            if (!\is_string($page) || '' === $page) {
                throw new \InvalidArgumentException(\sprintf('devkit.fleet.modules.%s must name the route of the first page the module answers (page).', $name));
            }
            $modules[$name] = new ModuleUnderGate($name, $page);
        }

        $afterMigrate = self::words($config['after_migrate'] ?? []);

        return new self(
            self::words($config['official_modules'] ?? []),
            [] === $afterMigrate ? self::AFTER_MIGRATE : $afterMigrate,
            $modules,
        );
    }

    /**
     * The modules a run installs: the ones asked for, or every official one.
     *
     * @param list<string> $only
     *
     * @return list<ModuleUnderGate>
     */
    public function resolve(array $only = []): array
    {
        $resolved = [];
        foreach ([] !== $only ? $only : $this->officialModules as $name) {
            $resolved[] = $this->modules[$name]
                ?? throw new \InvalidArgumentException(\sprintf('The fleet knows no module "%s". Describe it under devkit.fleet.modules first; it knows: %s.', $name, implode(', ', array_keys($this->modules)) ?: 'none'));
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private static function words(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $word): string => \is_string($word) ? $word : throw new \InvalidArgumentException('Every devkit.fleet list holds strings.'),
            $value,
        ));
    }
}
