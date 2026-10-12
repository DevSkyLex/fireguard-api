<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Federation;

use Auth\Application\Contract\Federation\{FederatedFlow, ProviderId};

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
   * @param ProviderId $provider the provider
   * @param string $intent the intent
   *
   * @return ?FederatedFlow
   */
  public function consume(
    string $rawState,
    string $browserBinding,
    ProviderId $provider,
    string $intent,
  ): ?FederatedFlow;

  /**
   * Method inspect
   *
   * Verifies state, provider, intent and browser binding without consuming or opening encrypted exchange proof.
   * The projection omits the code verifier and verified profile. Completed tombstones remain inspectable after
   * result expiry so current account/session authorization precedes the engine's expired-result acknowledgement.
   *
   * @access public
   *
   * @param string $rawState original OAuth state
   * @param string $browserBinding browser-held flow secret
   * @param ProviderId $provider exact current provider
   * @param string $intent login or link
   *
   * @return ?FederatedFlow current validated stage
   */
  public function inspect(string $rawState, string $browserBinding, ProviderId $provider, string $intent): ?FederatedFlow;

  /**
   * Method recordVerified
   *
   * Commits the verified provider identity independently before local issuance; retained proof can resume local rollback without repeating remote exchange.
   *
   * @access public
   *
   * @param string $stateHash reserved flow identity
   * @param \Auth\Application\Contract\Federation\FederatedProfile $profile cryptographically verified provider facts
   *
   * @return void
   */
  public function recordVerified(string $stateHash, \Auth\Application\Contract\Federation\FederatedProfile $profile): void;

  /**
   * Method markCompleted
   *
   * Completes the flow inside the same auth transaction as the local credential result and idempotency receipt.
   *
   * @access public
   *
   * @param string $stateHash verified flow identity
   * @param string $userId resulting account owner
   *
   * @return void
   */
  public function markCompleted(string $stateHash, string $userId): void;
  // #endregion
}
