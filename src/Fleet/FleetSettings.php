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
 * order they install, the commands every install is followed by, what the
 * gate has to be told about each module, and the packs that bring them. Adding a module is a line of an
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
     * @param array<string, PackUnderGate>   $packs           every pack, by its alias
     */
    public function __construct(
        public array $officialModules,
        public array $afterMigrate,
        public array $modules,
        public array $packs = [],
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

        $packs = [];
        $configured = \is_array($config['packs'] ?? null) ? $config['packs'] : [];
        foreach ($configured as $name => $names) {
            $name = (string) $name;
            foreach ($names = self::words($names) as $module) {
                if (!isset($modules[$module])) {
                    throw new \InvalidArgumentException(\sprintf('devkit.fleet.packs.%s names "%s", a module the fleet does not know. Describe it under devkit.fleet.modules first.', $name, $module));
                }
            }
            $packs[$name] = new PackUnderGate($name, $names);
        }

        $afterMigrate = self::words($config['after_migrate'] ?? []);

        return new self(
            self::words($config['official_modules'] ?? []),
            [] === $afterMigrate ? self::AFTER_MIGRATE : $afterMigrate,
            $modules,
            $packs,
        );
    }

    /**
     * The packs a run installs whole: a pack with a module the run leaves out
     * would bring that module anyway.
     *
     * @param list<ModuleUnderGate> $modules the modules the run installs
     *
     * @return list<PackUnderGate>
     */
    public function packsWithin(array $modules): array
    {
        $names = array_map(static fn (ModuleUnderGate $module): string => $module->name, $modules);

        return array_values(array_filter($this->packs, static fn (PackUnderGate $pack): bool => [] === array_diff($pack->modules, $names)));
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
