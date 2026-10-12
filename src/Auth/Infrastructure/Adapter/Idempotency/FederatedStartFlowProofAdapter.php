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
    if (false === $row || !is_string($row['expires_at'] ?? null)) {
      return null;
    }
    $expiry = new DateTimeImmutable($row['expires_at'], new DateTimeZone('UTC'));
    if ($expiry <= $this->clock->now()) {
      return null;
    }
    $userId = $row['user_id'] ?? null;
    if (null !== $userId && !is_string($userId)) {
      return null;
    }

    return new FederatedStartFlowProof($stateHash, $bindingHash, $provider, $intent, $userId, $expiry);
  }
}
