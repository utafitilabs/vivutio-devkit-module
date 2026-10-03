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
 * What a step of the gate does.
 */
enum GateStepKind
{
    /** Run a command, in the project or beside it, and fail on a non-zero exit. */
    case Shell;

    /**
     * `composer validate --strict` in the skeleton's checkout. A stale lock
     * only warns on create-project and installs on, so without it the gate
     * would prove a project the skeleton's own CI refuses.
     */
    case ValidateSkeleton;

    /** The skeleton's README lists every official module the gate installs. */
    case ReadmeListsModules;

    /** Drop and recreate the database the step's subject names. */
    case FreshDatabase;

    /** Write the project's .env.local: the databases the gate made. */
    case WriteEnvironment;

    /** Serve the project, or serve it again after an install changed it. */
    case Serve;

    /** Sign in over HTTP as the administrator the gate created. */
    case SignIn;

    /** Open a page, named by its route, as the administrator. */
    case OpenPage;
}
