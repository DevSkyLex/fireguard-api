<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use JsonException;
use LogicException;
use Shared\Application\Contract\Idempotency\{IdempotencyCompletion, IdempotencyDeniedException, IdempotencyLease, IdempotencyReceipt};
use Shared\Application\Port\Outbound\Idempotency\{IdempotencyReceiptPort, IdempotencyRetentionPort};

use function hash_equals;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class DoctrineIdempotencyReceiptRepository
 *
 * Uses one named owning database and an independent reservation connection.
 * Durable writing markers prevent lease takeover after unknown commit outcomes.
 *
 * @category Repository
 */
final class DoctrineIdempotencyReceiptRepository implements IdempotencyReceiptPort, IdempotencyRetentionPort
{
  // #region Properties
  /**
   * @var array<string, true> transaction-local claim fences
   */
  private array $claimed = [];

  /**
   * Property reservationConnection
   */
  private ?Connection $reservationConnection;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection explicitly named auth or main write connection
   * @param Connection|null $reservationConnection independent connection to the same database
   *
   * @return void
   */
  public function __construct(private readonly Connection $connection, ?Connection $reservationConnection = null)
  {
    if ($connection === $reservationConnection) {
      throw new LogicException('Idempotency reservation requires an independent connection.');
    }
    $this->reservationConnection = $reservationConnection;
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function reserve(IdempotencyLease $candidate, DateTimeImmutable $now): IdempotencyReceipt
  {
    $this->assertOutsideWriteTransaction();
    $reservation = $this->reservation();
    // Existing writing rows must be inspected without an INSERT waiting for an
    // unacknowledged primary commit on the same unique key.
    $receipt = $this->read($reservation, $candidate);
    if (null === $receipt) {
      $affected = (int) $reservation->executeStatement(
        'INSERT INTO http_idempotency_receipts (scope_hash, key_hash, fingerprint, owner_token, principal_hash, organization_hash, state, lease_expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (scope_hash, key_hash) DO NOTHING',
        [$candidate->scopeHash, $candidate->keyHash, $candidate->fingerprint, $candidate->ownerToken, $candidate->principalHash, $candidate->organizationHash, 'pending', $this->timestamp($candidate->expiresAt), $this->timestamp($now)],
      );
      if (1 === $affected) {
        return new IdempotencyReceipt($candidate->fingerprint, $candidate->ownerToken, 'pending', $candidate->expiresAt);
      }
      $receipt = $this->read($reservation, $candidate);
    }
    if (null === $receipt) {
      throw new IdempotencyDeniedException('in_progress');
    }
    if ('pending' !== $receipt->state || $receipt->leaseExpiresAt > $now || !hash_equals($candidate->fingerprint, $receipt->fingerprint)) {
      return $receipt;
    }

    return $this->takeOverExpiredReservation($reservation, $candidate, $now);
  }

  /**
   * {@inheritdoc}
   */
  public function claim(IdempotencyLease $lease, DateTimeImmutable $now, ?string $organizationHash = null): void
  {
    $this->assertWriteTransaction();
    // Commit this marker before taking the primary lock. A lost primary connection
    // can never expose a takeover-eligible pending reservation after work started.
    $changed = (int) $this->reservation()->executeStatement(
      'UPDATE http_idempotency_receipts SET state = ?, organization_hash = COALESCE(?, organization_hash) WHERE scope_hash = ? AND key_hash = ? AND owner_token = ? AND fingerprint = ? AND state = ? AND lease_expires_at > ?',
      ['writing', $organizationHash, $lease->scopeHash, $lease->keyHash, $lease->ownerToken, $lease->fingerprint, 'pending', $this->timestamp($now)],
    );
    if (1 !== $changed) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    $receipt = $this->read($this->connection, $lease, true);
    if (null === $receipt || 'writing' !== $receipt->state || !hash_equals($lease->ownerToken, $receipt->ownerToken) || !hash_equals($lease->fingerprint, $receipt->fingerprint)) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    $this->claimed[$lease->ownerToken] = true;
  }

  /**
   * {@inheritdoc}
   */
  public function complete(IdempotencyLease $lease, IdempotencyCompletion $completion): void
  {
    $this->assertWriteTransaction();
    if (!isset($this->claimed[$lease->ownerToken])) {
      throw new LogicException('Idempotency result requires a claimed owning transaction.');
    }
    $changed = (int) $this->connection->executeStatement(
      'UPDATE http_idempotency_receipts SET state = ?, result_payload = ?, secret_result = ?, completed_at = ?, result_expires_at = ?, proof_payload = ?, proof_mac = ?, organization_hash = COALESCE(?, organization_hash) WHERE scope_hash = ? AND key_hash = ? AND owner_token = ? AND fingerprint = ? AND state = ?',
      ['completed', $completion->payload, $completion->secret ? 'true' : 'false', $this->timestamp($completion->completedAt), $this->timestamp($completion->expiresAt), json_encode($completion->proof, JSON_THROW_ON_ERROR), $completion->proofMac, $completion->organizationHash, $lease->scopeHash, $lease->keyHash, $lease->ownerToken, $lease->fingerprint, 'writing'],
    );
    if (1 !== $changed) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    unset($this->claimed[$lease->ownerToken]);
  }

  /**
   * {@inheritdoc}
   */
  public function releaseAfterRollback(IdempotencyLease $lease): void
  {
    $this->assertOutsideWriteTransaction();
    $this->reservation()->executeStatement(
      "UPDATE http_idempotency_receipts SET state = 'pending', owner_token = '', lease_expires_at = TIMESTAMPTZ 'epoch' WHERE scope_hash = ? AND key_hash = ? AND owner_token = ? AND state IN ('pending', 'writing')",
      [$lease->scopeHash, $lease->keyHash, $lease->ownerToken],
    );
    unset($this->claimed[$lease->ownerToken]);
  }

  /**
   * {@inheritdoc}
   */
  public function markUnknown(IdempotencyLease $lease): void
  {
    unset($this->claimed[$lease->ownerToken]);

    // NOWAIT preserves fail-closed writing state if the primary transaction is
    // still in flight. A completed row is never downgraded.
    try {
      $this->reservation()->transactional(function (Connection $reservation) use ($lease): void {
        $receipt = $this->read($reservation, $lease, true);
        if (null !== $receipt && hash_equals($lease->ownerToken, $receipt->ownerToken) && ('writing' === $receipt->state || 'pending' === $receipt->state)) {
          $reservation->executeStatement(
            'UPDATE http_idempotency_receipts SET state = ? WHERE scope_hash = ? AND key_hash = ? AND owner_token = ?',
            ['outcome_unknown', $lease->scopeHash, $lease->keyHash, $lease->ownerToken],
          );
        }
      });
    } catch (DriverException $exception) {
      if ('55P03' !== $exception->getSQLState()) {
        throw $exception;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function find(IdempotencyLease $lease): ?IdempotencyReceipt
  {
    return $this->read($this->reservation(), $lease);
  }

  /**
   * {@inheritdoc}
   */
  public function purgeScope(string $scopeHash): void
  {
    $this->assertOutsideWriteTransaction();
    $this->reservation()->executeStatement('DELETE FROM http_idempotency_receipts WHERE scope_hash = ?', [$scopeHash]);
  }

  /**
   * {@inheritdoc}
   */
  public function purgePrincipal(string $principalHash, ?string $associatedPrincipalId = null): void
  {
    $this->assertOutsideWriteTransaction();
    // Finalized, owner-verified user associations also cover client-scoped and federated grants.
    $this->reservation()->executeStatement("DELETE FROM http_idempotency_receipts WHERE principal_hash = ? OR (proof_payload::jsonb ->> 'user_id') = ?", [$principalHash, $associatedPrincipalId]);
  }

  /**
   * {@inheritdoc}
   */
  public function purgeOrganization(string $organizationHash): void
  {
    $this->assertOutsideWriteTransaction();
    $this->reservation()->executeStatement('DELETE FROM http_idempotency_receipts WHERE organization_hash = ?', [$organizationHash]);
  }

  /**
   * Method eraseExpiredResults
   *
   * Erases expired plaintext/ciphertext while keeping non-reusable tombstones.
   *
   * @access public
   *
   * @param DateTimeImmutable $now retention deadline
   *
   * @return int erased payload count
   */
  public function eraseExpiredResults(DateTimeImmutable $now): int
  {
    $this->assertOutsideWriteTransaction();

    return (int) $this->reservation()->executeStatement('UPDATE http_idempotency_receipts SET result_payload = NULL WHERE state = ? AND result_expires_at <= ? AND result_payload IS NOT NULL', ['completed', $this->timestamp($now)]);
  }

  /**
   * Method takeOverExpiredReservation
   *
   * Rechecks the pending fence under a nonblocking lock before replacing its owner.
   *
   * @access private
   *
   * @param Connection $reservation the independent owning database connection
   * @param IdempotencyLease $candidate the proposed new owner
   * @param DateTimeImmutable $now the reservation time
   *
   * @return IdempotencyReceipt the locked authoritative fence
   */
  private function takeOverExpiredReservation(Connection $reservation, IdempotencyLease $candidate, DateTimeImmutable $now): IdempotencyReceipt
  {

    try {
      return $reservation->transactional(function (Connection $reservation) use ($candidate, $now): IdempotencyReceipt {
        $existing = $this->read($reservation, $candidate, true);
        if (null === $existing) {
          throw new IdempotencyDeniedException('in_progress');
        }
        if ('pending' !== $existing->state || $existing->leaseExpiresAt > $now || !hash_equals($candidate->fingerprint, $existing->fingerprint)) {
          return $existing;
        }
        $changed = (int) $reservation->executeStatement(
          'UPDATE http_idempotency_receipts SET owner_token = ?, lease_expires_at = ? WHERE scope_hash = ? AND key_hash = ? AND owner_token = ? AND state = ?',
          [$candidate->ownerToken, $this->timestamp($candidate->expiresAt), $candidate->scopeHash, $candidate->keyHash, $existing->ownerToken, 'pending'],
        );
        if (1 !== $changed) {
          throw new IdempotencyDeniedException('owner_lost');
        }

        return new IdempotencyReceipt($candidate->fingerprint, $candidate->ownerToken, 'pending', $candidate->expiresAt);
      });
    } catch (DriverException $exception) {
      if ('55P03' !== $exception->getSQLState()) {
        throw $exception;
      }

      throw new IdempotencyDeniedException('in_progress');
    }
  }

  /**
   * Method reservation
   *
   * @access private
   *
   * @return Connection independent physical connection to the owning database
   */
  private function reservation(): Connection
  {
    $this->reservationConnection ??= DriverManager::getConnection($this->connection->getParams());
    if ($this->reservationConnection->isTransactionActive()) {
      throw new LogicException('The reservation connection must own its short transactions.');
    }

    return $this->reservationConnection;
  }

  /**
   * Method read
   *
   * @access private
   *
   * @param Connection $connection owning read connection
   * @param IdempotencyLease $lease scoped identity
   * @param bool $lock acquire a NOWAIT row lock until transaction completion
   *
   * @return IdempotencyReceipt|null authoritative persisted receipt
   */
  private function read(Connection $connection, IdempotencyLease $lease, bool $lock = false): ?IdempotencyReceipt
  {
    $row = $connection->fetchAssociative('SELECT fingerprint, owner_token, state, lease_expires_at, result_payload, secret_result, result_expires_at, proof_payload, proof_mac FROM http_idempotency_receipts WHERE scope_hash = ? AND key_hash = ?' . ($lock ? ' FOR UPDATE NOWAIT' : ''), [$lease->scopeHash, $lease->keyHash]);
    if (false === $row) {
      return null;
    }

    /** @var array{fingerprint: string, owner_token: string, state: string, lease_expires_at: string, result_payload: ?string, secret_result: bool|string|int, result_expires_at: ?string, proof_payload: ?string, proof_mac: ?string} $row */
    return new IdempotencyReceipt(
      $row['fingerprint'],
      $row['owner_token'],
      $row['state'],
      new DateTimeImmutable($row['lease_expires_at']),
      $row['result_payload'],
      true === $row['secret_result'] || 't' === $row['secret_result'] || 'true' === $row['secret_result'] || 1 === $row['secret_result'],
      null === $row['result_expires_at'] ? null : new DateTimeImmutable($row['result_expires_at']),
      $this->proofMetadata($row['proof_payload']),
      $row['proof_mac'],
    );
  }

  /**
   * Method proofMetadata
   *
   * @access private
   *
   * @param string|null $payload persisted non-secret proof bytes
   *
   * @return array<string, string> scalar proof metadata
   */
  private function proofMetadata(?string $payload): array
  {
    if (null === $payload) {
      return [];
    }

    try {
      $decoded = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (!is_array($decoded)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $proof = [];
    foreach ($decoded as $name => $value) {
      if (!is_string($name) || !is_string($value)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $proof[$name] = $value;
    }

    return $proof;
  }

  /**
   * Method assertOutsideWriteTransaction
   *
   * @access private
   *
   * @return void
   */
  private function assertOutsideWriteTransaction(): void
  {
    if ($this->connection->isTransactionActive()) {
      throw new LogicException('Reservation or rollback release requires no active write transaction.');
    }
  }

  /**
   * Method assertWriteTransaction
   *
   * @access private
   *
   * @return void
   */
  private function assertWriteTransaction(): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Idempotency claim/result must share the owning write transaction.');
    }
  }

  /**
   * Method timestamp
   *
   * @access private
   *
   * @param DateTimeImmutable $value absolute instant
   *
   * @return string timezone-aware PostgreSQL timestamp
   */
  private function timestamp(DateTimeImmutable $value): string
  {
    return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
  }
  // #endregion
}
