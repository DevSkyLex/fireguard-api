<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyLease, IdempotencyProof, IdempotencyScope, IdempotencySnapshot};

/**
 * Interface HttpIdempotencyPort
 *
 * Couples an authorized HTTP attempt to its owning durable application transaction.
 * Replays require the caller to reverify authentication, permissions and current scope.
 *
 * @category Outbound Port
 */
interface HttpIdempotencyPort
{
  // #region Methods
  /**
   * Method begin
   *
   * Reserves outside the write transaction or returns the committed result.
   * A replay gate receives the proof of the selected receipt before any result bytes open.
   *
   * @access public
   *
   * @param IdempotencyScope $scope currently verified operation scope
   * @param string $key opaque client idempotency key
   * @param mixed $canonicalInput scalar/list/map input including preconditions and meaningful query data
   * @param (callable(IdempotencyProof):void)|null $authorizeReplay current owner gate for the selected completed receipt
   *
   * @return IdempotencyBegin ownership or replay
   */
  public function begin(IdempotencyScope $scope, string $key, mixed $canonicalInput, ?callable $authorizeReplay = null): IdempotencyBegin;

  /**
   * Method inspectProof
   *
   * Publishes authenticated non-secret proof before opening a completed result.
   * The caller must verify its credential and current scope before calling.
   *
   * @access public
   *
   * @param IdempotencyScope $scope currently verified principal scope
   * @param string $key client receipt key, never an authentication credential
   *
   * @return IdempotencyProof|null authenticated nonsecret completed proof, including an expired result tombstone
   */
  public function inspectProof(IdempotencyScope $scope, string $key): ?IdempotencyProof;

  /**
   * Method claimWithinTransaction
   *
   * Fences ownership before side effects and holds its row lock through the owning commit.
   *
   * @access public
   *
   * @param IdempotencyLease $lease reserved attempt
   * @param string|null $verifiedOrganizationId fresh verified owner organization for lifecycle indexing
   *
   * @return void
   */
  public function claimWithinTransaction(IdempotencyLease $lease, ?string $verifiedOrganizationId = null): void;

  /**
   * Method beforeCommit
   *
   * Persists the application result inside the same transaction as the business writes.
   *
   * @access public
   *
   * @param IdempotencyLease $lease claimed attempt
   * @param IdempotencySnapshot $snapshot application result captured before commit
   *
   * @return void
   */
  public function beforeCommit(IdempotencyLease $lease, IdempotencySnapshot $snapshot): void;

  /**
   * Method confirmedRollback
   *
   * Releases only this ownership after a confirmed root rollback; never call for unknown outcomes.
   *
   * @access public
   *
   * @param IdempotencyLease $lease rolled-back attempt
   *
   * @return void
   */
  public function confirmedRollback(IdempotencyLease $lease): void;

  /**
   * Method commitUnknown
   *
   * Blocks automatic takeover after a commit or rollback could not be confirmed.
   *
   * @access public
   *
   * @param IdempotencyLease $lease uncertain attempt
   *
   * @return void
   */
  public function commitUnknown(IdempotencyLease $lease): void;

  /**
   * Method reconcile
   *
   * Reads a durable completed result; an absent or uncertain result remains blocked.
   *
   * @access public
   *
   * @param IdempotencyLease $lease uncertain attempt
   * @param (callable(IdempotencyProof):void)|null $authorizeReplay current owner gate for the selected completed receipt
   *
   * @return IdempotencySnapshot|null committed result if visible
   */
  public function reconcile(IdempotencyLease $lease, ?callable $authorizeReplay = null): ?IdempotencySnapshot;

  /**
   * Method purgeScope
   *
   * Deletes tombstones only when the owning scope lifecycle is definitively purged.
   *
   * @access public
   *
   * @param IdempotencyScope $scope permanently purged authorized scope
   *
   * @return void
   */
  public function purgeScope(IdempotencyScope $scope): void;

  /**
   * Method purgePrincipal
   *
   * @access public
   *
   * @param string $principalId permanently deleted principal
   *
   * @return void
   */
  public function purgePrincipal(string $principalId): void;

  /**
   * Method purgeOrganization
   *
   * @access public
   *
   * @param string $organizationId permanently deleted organization
   *
   * @return void
   */
  public function purgeOrganization(string $organizationId): void;
  // #endregion
}
