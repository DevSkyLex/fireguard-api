<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\IdempotencyLease;

/**
 * Port ExternalIdempotencyReconciliationPort.
 *
 * Owners durably request current-state reconciliation after an uncertain external effect.
 * The caller has ended the owning SQL transaction; a provider mutation must not be repeated blindly.
 *
 * @category Port
 */
interface ExternalIdempotencyReconciliationPort
{
  /**
   * Identifies the owner's explicit operation names.
   */
  public function supports(string $operationName): bool;

  /**
   * @param array<string, string> $verifiedProof nonsecret scope facts from the fresh owner gate
   * @param list<string> $purposes attempted external effects, without credentials or destinations
   */
  public function requestReconciliation(HttpIdempotencyOperation $operation, IdempotencyLease $lease, array $verifiedProof, array $purposes): void;
}
