<?php

declare(strict_types=1);

namespace Shared\Application\Port\Inbound;

/**
 * Interface SeedReferenceConsumerPort
 *
 * Supplies a seed owner with pure reference access during composition.
 *
 * @category Port
 */
interface SeedReferenceConsumerPort
{
  // #region Methods
  /**
   * Method setSeedReferences
   *
   * @access public
   *
   * @param SeedReferenceRegistryPort $references scalar facts published by prerequisite seed steps
   *
   * @return void
   */
  public function setSeedReferences(SeedReferenceRegistryPort $references): void;
  // #endregion
}
