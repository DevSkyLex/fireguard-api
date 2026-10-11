<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

use Shared\Application\Contract\Seed\SeedReference;

/**
 * Interface SeedStepPort
 *
 * Publishes a named module-owned seed capability and its scalar prerequisite identities.
 *
 * @category Port
 */
interface SeedStepPort
{
  // #region Methods
  /**
   * Method key
   *
   * @access public
   *
   * @return string stable module seed group identity
   */
  public function key(): string;

  /**
   * Method dependencies
   *
   * @access public
   *
   * @return list<string> prerequisite group identities, never private class names
   */
  public function dependencies(): array;

  /**
   * Method seed
   *
   * @access public
   *
   * @return array<string, SeedReference> identifiers and permitted scalar facts created by this step
   */
  public function seed(): array;
  // #endregion
}
