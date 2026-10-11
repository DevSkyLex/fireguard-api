<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

use OutOfBoundsException;
use Shared\Application\Contract\Seed\SeedReference;

/**
 * Interface SeedReferenceRegistryPort
 *
 * Resolves only scalar facts from completed seed steps; credentials and persistence objects stay private.
 *
 * @category Port
 */
interface SeedReferenceRegistryPort
{
  // #region Methods
  /**
   * Method get
   *
   * @access public
   *
   * @param string $reference stable fixture reference
   *
   * @return SeedReference the published fixture facts
   *
   * @throws OutOfBoundsException when a prerequisite has not published this reference
   */
  public function get(string $reference): SeedReference;
  // #endregion
}
