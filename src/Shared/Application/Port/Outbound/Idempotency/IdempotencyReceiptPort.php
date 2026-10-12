<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use DateTimeImmutable;
use Shared\Application\Contract\Idempotency\{IdempotencyCompletion, IdempotencyLease, IdempotencyReceipt};

/**
 * Interface IdempotencyReceiptPort
 *
 * Owns same-database durable reservations and fenced application-result writes.
 *
 * @category Outbound Port
 */
interface IdempotencyReceiptPort
{
  // #region Methods
  /**
   * Method reserve
   *
   * @access public
   *
   * @param IdempotencyLease $candidate fresh candidate fence
   * @param DateTimeImmutable $now current reservation time
   *
   * @return IdempotencyReceipt authoritative receipt
   */
  public function reserve(IdempotencyLease $candidate, DateTimeImmutable $now): IdempotencyReceipt;

  /**
   * Method claim
   *
   * @access public
   *
   * @param IdempotencyLease $lease reserved fence
   * @param DateTimeImmutable $now current claim time
   * @param string|null $organizationHash verified organization lifecycle digest
   *
   * @return void
   */
  public function claim(IdempotencyLease $lease, DateTimeImmutable $now, ?string $organizationHash = null): void;

  /**
   * Method complete
   *
   * @access public
   *
   * @param IdempotencyLease $lease claimed fence
   * @param IdempotencyCompletion $completion opaque result with its immutable replay deadline and authenticated proof
   *
   * @return void
   */
  public function complete(IdempotencyLease $lease, IdempotencyCompletion $completion): void;

  /**
   * Method releaseAfterRollback
   *
   * @access public
   *
   * @param IdempotencyLease $lease confirmed rollback fence
   *
   * @return void
   */
  public function releaseAfterRollback(IdempotencyLease $lease): void;

  /**
   * Method markUnknown
   *
   * @access public
   *
   * @param IdempotencyLease $lease uncertain fence
   *
   * @return void
   */
  public function markUnknown(IdempotencyLease $lease): void;

  /**
   * Method find
   *
   * @access public
   *
   * @param IdempotencyLease $lease scoped receipt identity
   *
   * @return IdempotencyReceipt|null durable receipt
   */
  public function find(IdempotencyLease $lease): ?IdempotencyReceipt;

  /**
   * Method purgeScope
   *
   * @access public
   *
   * @param string $scopeHash lifecycle-authorized scope digest
   *
   * @return void
   */
  public function purgeScope(string $scopeHash): void;

  /**
   * Method purgePrincipal
   *
   * @access public
   *
   * @param string $principalHash permanently deleted principal digest
   * @param string|null $associatedPrincipalId verified deleted account identity also named by finalized owner proof
   *
   * @return void
   */
  public function purgePrincipal(string $principalHash, ?string $associatedPrincipalId = null): void;

  /**
   * Method purgeOrganization
   *
   * @access public
   *
   * @param string $organizationHash permanently deleted organization digest
   *
   * @return void
   */
  public function purgeOrganization(string $organizationHash): void;
  // #endregion
}
