# vivutio/devkit-module

The vivutio development kit: the fleet gate, which creates a project from the
skeleton in an empty directory, installs the core and every official module
into it, and proves the platform installs and runs as one product.

Development only. It is installed through `require-dev`, which is the
production firewall: a command that creates projects, drops databases and
starts servers never reaches an installation's production container.

## Contents

- [Installation](#installation)
- [The fleet gate](#the-fleet-gate)
- [Configuration](#configuration)
- [Licence](#licence)

## Installation

```bash
composer require --dev vivutio/devkit-module
```

and enable it for development in `config/bundles.php`:

```php
Vivutio\Devkit\VivutioDevkitBundle::class => ['dev' => true, 'test' => true],
```

## The fleet gate

```bash
php bin/console fleet:gate --mode=head   # before a tag: the sibling checkouts
php bin/console fleet:gate               # after a tag: the published repositories
php bin/console fleet:gate --dry-run     # the plan, and none of it run
```

What it does, step by step, and how to read a red run:
[docs/fleet-gate.md](docs/fleet-gate.md).

## Configuration

What the fleet is made of is configuration, so adding an official module is a
line here and never a release of this kit:

```yaml
# config/packages/devkit.yaml
devkit:
    fleet:
        official_modules: ['property']            # install order
        after_migrate: ['doctrine:migrations:migrate', 'cache:warmup']
        modules:
            property: { page: 'property_index' }  # the route of its first page
```

## Licence

**AGPL-3.0-or-later**: see [LICENSE](LICENSE).
