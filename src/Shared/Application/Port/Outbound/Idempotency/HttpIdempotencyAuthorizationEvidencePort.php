<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

/**
 * Interface HttpIdempotencyAuthorizationEvidencePort
 *
 * Collects only successful server-side authorization requirements during the current original HTTP execution.
 *
 * @category Port
 */
interface HttpIdempotencyAuthorizationEvidencePort
{
  // #region Methods
  /**
   * Method begin
   *
   * Binds evidence to a server-verified principal before the owner's current gate records its successful requirements.
   *
   * @access public
   *
   * @param string $principalId current authenticated or freshly proved owner identity
   *
   * @return void
   */
  public function begin(string $principalId): void;

  /**
   * Method clear
   *
   * @access public
   *
   * @return void
   */
  public function clear(): void;

  /**
   * Method record
   *
   * @access public
   *
   * @param string $principalId actually checked current caller
   * @param string $organizationId organization against which access was checked
   * @param list<string> $permissions required registered permissions, never the caller's full claimed grant set
   * @param bool $membershipOnly whether active membership itself was required
   *
   * @return void
   */
  public function record(string $principalId, string $organizationId, array $permissions, bool $membershipOnly = false): void;

  /**
   * Method snapshot
   *
   * @access public
   *
   * @return array<string, string> finite scalar requirement metadata for signing before the owning commit
   */
  public function snapshot(): array;
  // #endregion
}
