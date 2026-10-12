<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Idempotency\IdempotencyLease;
use Shared\Application\Message\ResultMessage;

/**
 * Interface HttpIdempotencyExecutionPort
 *
 * Publishes the current authorized HTTP execution to application owners without exposing request/framework objects.
 * Internal calls outside an active HTTP operation retain their existing behavior.
 *
 * @category Outbound Port
 */
interface HttpIdempotencyExecutionPort
{
  // #region Methods
  /**
   * Method captureResult
   *
   * Captures the primary command's versioned result while the owning transaction remains active.
   * Owners call this before commit; a post-commit result cannot create a successful receipt.
   *
   * @access public
   *
   * @param ResultMessage $result the primary application outcome
   *
   * @return void
   */
  public function captureResult(ResultMessage $result): void;

  /**
   * Method captureVoid
   *
   * Captures a successful command without a result payload before its owning commit.
   *
   * @access public
   *
   * @return void
   */
  public function captureVoid(): void;

  /**
   * Method executionLease
   *
   * @access public
   *
   * @return IdempotencyLease|null the current ownership fence, or null outside a fresh HTTP execution
   */
  public function executionLease(): ?IdempotencyLease;

  /**
   * Method providerKey
   *
   * Derives a stable provider key from the logical operation identity rather than its attempt fence.
   * No raw client key or request credential is returned.
   *
   * @access public
   *
   * @param string $purpose the owner-declared provider operation identity
   *
   * @return string|null the provider key, or null outside a fresh HTTP execution
   */
  public function providerKey(string $purpose): ?string;

  /**
   * Method externalEffectStarted
   *
   * Marks a potentially committed provider effect before issuing its request.
   * A later local rollback keeps this logical operation blocked for reconciliation.
   *
   * @access public
   *
   * @param string $purpose the owner-declared provider effect identity
   *
   * @return void
   */
  public function externalEffectStarted(string $purpose): void;

  /**
   * Method isReplay
   *
   * Reports a completed, input-matching replay only after the owner has reverified current access.
   *
   * @access public
   *
   * @return bool whether the current operation is an authorized completed replay
   */
  public function isReplay(): bool;
  // #endregion
}
