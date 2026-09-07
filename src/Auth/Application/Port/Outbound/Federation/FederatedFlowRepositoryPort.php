<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Federation;

use Auth\Application\Contract\Federation\FederatedFlow;
use Auth\Domain\ValueObject\Federation\FederatedProvider;

/**
 * Interface FederatedFlowRepositoryPort.
 *
 * Stores and atomically consumes short-lived OAuth flow state.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederatedFlowRepositoryPort
{
  public function save(FederatedFlow $flow): void;

  public function consume(
    string $rawState,
    string $browserBinding,
    FederatedProvider $provider,
    string $intent,
  ): ?FederatedFlow;
}
