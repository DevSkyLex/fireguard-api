<?php

declare(strict_types=1);

namespace Import\Application\Port\Outbound;

use Import\Application\Contract\{ImportKindDescriptor, ImportSessionContext};

/**
 * Interface ImportKindProcessorPort
 *
 * Owner modules contribute metadata and isolated row-processing sessions.
 * Contributors are reusable services; mutable projection belongs to each new session.
 *
 * @category Port
 */
interface ImportKindProcessorPort
{
  // #region Methods
  /**
   * Method descriptor
   *
   * @access public
   *
   * @return ImportKindDescriptor stable registration metadata
   */
  public function descriptor(): ImportKindDescriptor;

  /**
   * Method startSession
   *
   * Creating a session performs no provisioning and returns fresh job-local state.
   *
   * @access public
   *
   * @param ImportSessionContext $context immutable authorized execution context
   *
   * @return ImportRowSessionPort the isolated row processor
   */
  public function startSession(ImportSessionContext $context): ImportRowSessionPort;
  // #endregion
}
