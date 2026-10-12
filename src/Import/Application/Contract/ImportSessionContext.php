<?php

declare(strict_types=1);

namespace Import\Application\Contract;

/**
 * Contract ImportSessionContext
 *
 * Immutable execution identity; contributors never receive the import aggregate or lease.
 *
 * @category Contract
 */
final readonly class ImportSessionContext
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $jobId retained job identity used for stable simulation identifiers
   * @param string $organizationId owning organization
   * @param string $actorId currently authorized initiating or resuming actor
   * @param bool $dryRun whether provisioning must remain a simulation
   *
   * @return void
   */
  public function __construct(
    public string $jobId,
    public string $organizationId,
    public string $actorId,
    public bool $dryRun,
  ) {
  }
  // #endregion
}
