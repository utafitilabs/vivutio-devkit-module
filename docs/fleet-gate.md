# The fleet gate

`fleet:gate` creates a project from the skeleton with the skeleton's own
install guide and installs the platform into it, one official module at a
time. It is the last check before a release, and the first after one.

## Contents

- [What it proves](#what-it-proves)
- [The two modes](#the-two-modes)
- [Running it](#running-it)
- [What it does, step by step](#what-it-does-step-by-step)
- [Why it scrubs its environment](#why-it-scrubs-its-environment)
- [Reading a red run](#reading-a-red-run)

## What it proves

Every repository tests itself at its own head: the core's suite, the
skeleton's, each module's. None of them performs an install from nothing. A
module can be pushed green and never tagged, and the tag a fresh install then
resolves names classes the core no longer has: every build green, the install
broken. The gate performs the install, so it answers the one question the other
suites cannot: does the platform install and run as one product?

## The two modes

| | `fleet:gate` | `fleet:gate --mode=head` |
|---|---|---|
| takes the packages from | the published repositories, as the install guide does | the sibling checkouts, each at the branch it has out; commit first |
| answers | does the released platform install and run | would it, if everything were tagged now |
| runs | after any tag | before a tag |

Before the core's first tag, head mode is the only mode that can pass: the
skeleton requires the core by version, and nothing is published yet.

## Running it

It needs PHP, composer, git and a PostgreSQL server. It runs from any
installation that has this kit:

```bash
php bin/console fleet:gate --mode=head --workspace=/path/to/the/checkouts
```

| Option | Default | What it is |
|---|---|---|
| `--mode` | `released` | `released` or `head` |
| `--workspace` | the directory holding this kit's checkout | head mode: where `vivutio/`, `vivutio-skeleton/` and the modules are |
| `--module` | the official list | a module to install instead; repeatable |
| `--database-url` | `FLEET_GATE_DATABASE_URL`, else `fleet_gate` on 127.0.0.1:5434 | the gate's own database. **It is dropped and recreated** |
| `--keep` | off | keep the created project for a look |
| `--dry-run` | off | print the plan and run none of it |

## What it does, step by step

1. Head mode only: the skeleton validates, and its README lists every official
   module.
2. The project is created from the skeleton. In head mode it is created from the
   skeleton's checkout without installing, then pointed at the core's checkout
   and installed from the branch it has out.
3. A fresh database, and the project's `.env.local` naming it.
4. The install guide: the migrations, `assets:install`, and the schema checked
   twice, the mapping and the database, so a package whose entity moved on
   without its migration fails here.
5. The first administrator, with `identity:user:create`.
6. The project is served with PHP's built-in server; the administrator signs in
   through the form, and the dashboard and the team list answer, each found by
   its route name, never a path written here.
7. Each official module in turn: installed, migrated and checked again, the
   project's own tests, signed in again, and its first page answering.

## Why it scrubs its environment

The gate is a command of one installation that runs the console, composer and
tests of another, and a child process inherits its parent's variables. The
parent booted through Dotenv and carries `SYMFONY_DOTENV_VARS`, the marker
naming every variable Dotenv set, and in the child that marker makes the
project's `.env` overwrite what phpunit forces: its tests would run as `dev`.
So every child is given an environment with the marker, the variables it
names, `APP_DEBUG`, `KERNEL_CLASS` and `SHELL_VERBOSITY` removed, and only the
gate's databases added.

## Reading a red run

The report ends with `FLEET GATE RED at:`, the step, why, and the command.

- **Red in released mode, green in head mode**: a package is behind on its
  tags. Tag it and run released mode again.
- **Red in both**: the platform does not fit together at its head. That is a
  change in one repository, made with its own tests first.

`--keep` keeps the project and the report names where.
