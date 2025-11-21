<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Support;

/**
 * Enum ArchitectureNamespace
 *
 * Enumerates the namespaces shared by
 * architecture tests (root, shared, ports...)
 *
 * @category Architecture Support
 * @package App\Tests\Architecture\Support
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ArchitectureNamespace: string
{
  //#region Cases
  /**
   * Case ROOT
   *
   * Application root namespace.
   */
  case ROOT = 'App';

  /**
   * Case SHARED
   *
   * Shared kernel namespace.
   */
  case SHARED = 'Shared';

  /**
   * Case PORT
   *
   * Port sub-namespace.
   */
  case PORT = 'Port';
  //#endregion
}

