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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederatedFlowRepositoryPort
{
  // #region Methods
  /**
   * Method save
   *
   * Persists the federated flow under its state hash.
   *
   * @access public
   *
   * @param FederatedFlow $flow the flow
   *
   * @return void
   */
  public function save(FederatedFlow $flow): void;

  /**
   * Method consume
   *
   * Atomically consumes the federated flow for its state hash so it cannot be used again.
   *
   * @access public
   *
   * @param string $rawState the raw state
   * @param string $browserBinding the browser binding
   * @param FederatedProvider $provider the provider
   * @param string $intent the intent
   *
   * @return ?FederatedFlow
   */
  public function consume(
    string $rawState,
    string $browserBinding,
    FederatedProvider $provider,
    string $intent,
  ): ?FederatedFlow;
  // #endregion
}
