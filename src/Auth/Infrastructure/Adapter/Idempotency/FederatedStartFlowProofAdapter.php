<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Contract\Federation\{FederatedStartFlowProof, ProviderId};
use Auth\Application\Port\Outbound\Federation\FederatedStartFlowProofPort;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\ClockPort;

use function hash;
use function is_string;
use function preg_match;

/**
 * Class FederatedStartFlowProofAdapter
 *
 * Reads current Auth-owned flow stage without identity-map reuse, provider exchange or credential material.
 *
 * @category Adapter
 */
final readonly class FederatedStartFlowProofAdapter implements FederatedStartFlowProofPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection explicitly wired Auth-owned connection
   * @param ClockPort $clock current server time
   *
   * @return void
   */
  public function __construct(private Connection $connection, private ClockPort $clock)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function find(string $stateHash, string $browserBinding, ProviderId $provider, string $intent): ?FederatedStartFlowProof
  {
    if (1 !== preg_match('/^[a-f0-9]{64}$/D', $stateHash) || '' === $browserBinding) {
      return null;
    }
    $bindingHash = hash('sha256', $browserBinding);
    $row = $this->connection->fetchAssociative(
      "SELECT user_id, expires_at FROM federated_auth_flows WHERE state_hash = ? AND browser_binding_hash = ? AND provider = ? AND intent = ? AND stage = 'reserved' AND consumed_at IS NULL AND exchange_started_at IS NULL",
      [$stateHash, $bindingHash, $provider->value, $intent],
    );
    if (false === $row) {
      return null;
    }

    return $this->restore($row, $stateHash, $bindingHash, $provider, $intent);
  }

  /**
   * Method restore
   *
   * Validates the stored non-secret projection and its absolute deadline before exposing flow proof.
   *
   * @access private
   *
   * @param array<string, mixed> $row fresh Auth-owned reserved-flow projection
   * @param string $stateHash verified state identity
   * @param string $bindingHash verified browser identity
   * @param ProviderId $provider current provider
   * @param string $intent login or link
   *
   * @return ?FederatedStartFlowProof current unconsumed flow, absent when invalid or expired
   */
  private function restore(array $row, string $stateHash, string $bindingHash, ProviderId $provider, string $intent): ?FederatedStartFlowProof
  {
    if (!is_string($row['expires_at'] ?? null)) {
      return null;
    }
    $expiry = new DateTimeImmutable($row['expires_at'], new DateTimeZone('UTC'));
    $userId = $row['user_id'] ?? null;
    if ($expiry <= $this->clock->now() || (null !== $userId && !is_string($userId))) {
      return null;
    }

    return new FederatedStartFlowProof($stateHash, $bindingHash, $provider, $intent, $userId, $expiry);
  }
  // #endregion
}
