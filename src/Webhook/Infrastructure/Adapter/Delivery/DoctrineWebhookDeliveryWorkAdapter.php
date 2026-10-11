<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Delivery;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use LogicException;
use Webhook\Application\Contract\Delivery\PendingWebhookDelivery;
use Webhook\Application\Port\Outbound\Internal\WebhookDeliveryWorkPort;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Infrastructure\Persistence\Doctrine\Mapper\WebhookDeliveryMapper;
use Webhook\Infrastructure\Persistence\Doctrine\Record\WebhookDeliveryRecord;

use function array_map;
use function json_decode;
use function max;
use function min;

use const JSON_THROW_ON_ERROR;

/**
 * Class DoctrineWebhookDeliveryWorkAdapter
 *
 * Main-owned compare-and-set operations; no transaction remains open during HTTP.
 * Generation, retry-cycle and attempt identity fence every stale worker mutation.
 *
 * @category Adapter
 */
final readonly class DoctrineWebhookDeliveryWorkAdapter implements WebhookDeliveryWorkPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection explicitly wired owning main connection
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method claim
   *
   * Consumes only the envelope's reserved wake and claims only its current
   * technical cycle. Old reservations acquire cycle identity when consumed.
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param ?string $wakeToken
   * @param string $attemptToken
   * @param DateTimeImmutable $now
   *
   * @return ?WebhookDelivery
   */
  public function claim(string $id, int $generation, ?string $wakeToken, string $attemptToken, DateTimeImmutable $now): ?WebhookDelivery
  {
    if ($this->connection->isTransactionActive()) {
      throw new LogicException('Webhook HTTP delivery cannot run inside an outer main transaction.');
    }

    return $this->connection->transactional(function () use ($id, $generation, $wakeToken, $attemptToken, $now): ?WebhookDelivery {
      if (null !== $wakeToken) {
        $this->connection->executeStatement(
          'UPDATE webhook_deliveries SET command_token = CASE WHEN lease_until IS NULL OR lease_until <= :now THEN :command ELSE command_token END, wake_token = NULL, wake_at = NULL WHERE id = :id AND generation = :generation AND wake_token = :command',
          ['id' => $id, 'generation' => $generation, 'command' => $wakeToken, 'now' => $now],
          ['now' => 'datetime_immutable'],
        );
      }
      /** @var array<string, string|int|bool|null>|false $row */
      $row = $this->connection->fetchAssociative(
        "UPDATE webhook_deliveries SET attempt_token = :token, command_token = :command, lease_until = :lease, updated_at = :now, wake_token = NULL, wake_at = NULL WHERE id = :id AND generation = :generation AND command_token IS NOT DISTINCT FROM :command AND (wake_token IS NULL OR technical_failures > 0 OR attempt_token IS NOT NULL) AND status = 'pending' AND quarantined = FALSE AND attempts < 5 AND (lease_until IS NULL OR lease_until <= :now) AND (next_retry_at IS NULL OR next_retry_at <= :now) RETURNING *",
        ['id' => $id, 'generation' => $generation, 'token' => $attemptToken, 'command' => $wakeToken, 'lease' => $now->modify('+90 seconds'), 'now' => $now],
        ['lease' => 'datetime_immutable', 'now' => 'datetime_immutable'],
      );

      return false === $row ? null : $this->delivery($row);
    });
  }

  /**
   * Method startHttpAttempt
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param string $attemptToken
   *
   * @return bool
   */
  public function startHttpAttempt(string $id, int $generation, string $attemptToken): bool
  {
    return 1 === $this->connection->executeStatement("UPDATE webhook_deliveries SET attempts = attempts + 1 WHERE id = ? AND generation = ? AND attempt_token = ? AND status = 'pending' AND quarantined = FALSE AND attempts < 5 AND lease_until > CURRENT_TIMESTAMP", [$id, $generation, $attemptToken]);
  }

  /**
   * Method finish
   *
   * Releases the completed technical cycle and hands ownership to a concurrent
   * busy wake, if present, in the same statement that confirms the HTTP result.
   *
   * @access public
   *
   * @param WebhookDelivery $delivery
   * @param int $generation
   * @param string $attemptToken
   *
   * @return bool
   */
  public function finish(WebhookDelivery $delivery, int $generation, string $attemptToken): bool
  {
    return 1 === $this->connection->executeStatement(
      'UPDATE webhook_deliveries SET status = :status, http_status = :http, last_error = :error, next_retry_at = :retry, delivered_at = :delivered, updated_at = :updated, attempt_token = NULL, command_token = wake_token, lease_until = NULL, technical_failures = 0 WHERE id = :id AND generation = :generation AND attempt_token = :token AND lease_until > CURRENT_TIMESTAMP',
      ['id' => (string) $delivery->id(), 'generation' => $generation, 'token' => $attemptToken, 'status' => $delivery->status()->value, 'http' => $delivery->httpStatus(), 'error' => $delivery->lastError(), 'retry' => $delivery->nextRetryAt(), 'delivered' => $delivery->deliveredAt(), 'updated' => $delivery->updatedAt()],
      ['retry' => 'datetime_immutable', 'delivered' => 'datetime_immutable', 'updated' => 'datetime_immutable'],
    );
  }

  /**
   * Method reopen
   *
   * @access public
   *
   * @param string $id
   * @param string $subscriptionId
   * @param DateTimeImmutable $now
   *
   * @return ?int
   */
  public function reopen(string $id, string $subscriptionId, DateTimeImmutable $now): ?int
  {
    /** @var int|string|false $generation */
    $generation = $this->connection->fetchOne(
      "UPDATE webhook_deliveries SET generation = generation + 1, status = 'pending', attempts = 0, http_status = NULL, last_error = NULL, next_retry_at = NULL, delivered_at = NULL, attempt_token = NULL, command_token = NULL, lease_until = NULL, wake_token = NULL, wake_at = NULL, technical_failures = 0, quarantined = FALSE, updated_at = ? WHERE id = ? AND subscription_id = ? RETURNING generation",
      [$now, $id, $subscriptionId],
      ['datetime_immutable'],
    );

    return false === $generation ? null : (int) $generation;
  }

  /**
   * Method reserveWake
   *
   * Transfers retry-cycle ownership to the reserved wake once no active lease
   * protects the current command and retires the expired attempt. An in-flight
   * attempt keeps its technical budget until finalization or technical failure.
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param string $wakeToken
   * @param DateTimeImmutable $dueAt
   * @param DateTimeImmutable $now
   *
   * @return bool
   */
  public function reserveWake(string $id, int $generation, string $wakeToken, DateTimeImmutable $dueAt, DateTimeImmutable $now): bool
  {
    return 1 === $this->connection->executeStatement(
      "UPDATE webhook_deliveries SET wake_token = :token, wake_at = :due, command_token = CASE WHEN lease_until IS NULL OR lease_until <= :now THEN :token ELSE command_token END, attempt_token = CASE WHEN lease_until <= :now THEN NULL ELSE attempt_token END, lease_until = CASE WHEN lease_until <= :now THEN NULL ELSE lease_until END WHERE id = :id AND generation = :generation AND status = 'pending' AND quarantined = FALSE AND attempts < 5 AND (wake_token IS NULL OR wake_at <= :stale)",
      ['id' => $id, 'generation' => $generation, 'token' => $wakeToken, 'due' => $dueAt, 'now' => $now, 'stale' => $now->modify('-120 seconds')],
      ['due' => 'datetime_immutable', 'now' => 'datetime_immutable', 'stale' => 'datetime_immutable'],
    );
  }

  /**
   * Method recoverable
   *
   * @access public
   *
   * @param DateTimeImmutable $now
   * @param int $limit
   *
   * @return list<PendingWebhookDelivery>
   */
  public function recoverable(DateTimeImmutable $now, int $limit): array
  {
    // A crash during the fifth HTTP call still consumes that attempt, never a sixth.
    $this->connection->executeStatement("UPDATE webhook_deliveries SET status = 'failed', last_error = 'The delivery attempt budget is exhausted.', attempt_token = NULL, lease_until = NULL, wake_token = NULL, wake_at = NULL WHERE status = 'pending' AND attempts >= 5 AND (lease_until IS NULL OR lease_until <= ?)", [$now], ['datetime_immutable']);
    /** @var list<array{id: string, generation: int|string}> $rows */
    $rows = $this->connection->fetchAllAssociative(
      "SELECT id, generation FROM webhook_deliveries WHERE status = 'pending' AND quarantined = FALSE AND attempts < 5 AND (lease_until IS NULL OR lease_until <= :now) AND (next_retry_at IS NULL OR next_retry_at <= :now) AND (wake_token IS NULL OR wake_at <= :stale) ORDER BY created_at, id LIMIT " . max(1, min(1000, $limit)),
      ['now' => $now, 'stale' => $now->modify('-120 seconds')],
      ['now' => 'datetime_immutable', 'stale' => 'datetime_immutable'],
    );

    return array_map(static fn (array $row): PendingWebhookDelivery => new PendingWebhookDelivery((string) $row['id'], (int) $row['generation']), $rows);
  }

  /**
   * Method technicalFailure
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param string $attemptToken
   * @param DateTimeImmutable $now
   *
   * @return void
   */
  public function technicalFailure(string $id, int $generation, string $attemptToken, DateTimeImmutable $now): void
  {
    // Persist the technical budget so recovery with a fresh envelope cannot reset it.
    $this->connection->executeStatement(
      "UPDATE webhook_deliveries SET technical_failures = technical_failures + 1, quarantined = technical_failures >= 5, status = CASE WHEN technical_failures >= 5 THEN 'failed' ELSE status END, last_error = CASE WHEN technical_failures >= 5 THEN 'The delivery could not be completed.' ELSE last_error END, attempt_token = NULL, lease_until = NULL, wake_token = :token, wake_at = :retry WHERE id = :id AND generation = :generation AND attempt_token = :token",
      ['id' => $id, 'generation' => $generation, 'token' => $attemptToken, 'retry' => $now->modify('+3720 seconds')],
      ['retry' => 'datetime_immutable'],
    );
  }

  /**
   * Method quarantine
   *
   * Only the current technical retry cycle may quarantine an unleased delivery.
   * A fresh or historical reservation belongs to its exact wake; an unrelated
   * null command may only close its own unreserved or technically failed cycle.
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param ?string $commandToken
   *
   * @return void
   */
  public function quarantine(string $id, int $generation, ?string $commandToken = null): void
  {
    $this->connection->executeStatement("UPDATE webhook_deliveries SET quarantined = TRUE, status = 'failed', last_error = 'The delivery could not be completed.', attempt_token = NULL, lease_until = NULL, wake_token = NULL, wake_at = NULL WHERE id = :id AND generation = :generation AND status = 'pending' AND lease_until IS NULL AND ((command_token IS NOT DISTINCT FROM :command AND (wake_token IS NULL OR technical_failures > 0)) OR wake_token = :command)", ['id' => $id, 'generation' => $generation, 'command' => $commandToken]);
  }

  /**
   * Method delivery
   *
   * @access private
   *
   * @param array<string, string|int|bool|null> $row
   *
   * @return WebhookDelivery
   */
  private function delivery(array $row): WebhookDelivery
  {
    $record = new WebhookDeliveryRecord();
    $record->id = (string) $row['id'];
    $record->subscriptionId = (string) $row['subscription_id'];
    $record->organizationId = (string) $row['organization_id'];
    $record->eventType = (string) $row['event_type'];
    $record->eventId = (string) $row['event_id'];
    /** @var array<string, mixed> $payload */
    $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
    $record->payload = $payload;
    $record->status = (string) $row['status'];
    $record->attempts = (int) $row['attempts'];
    $record->generation = (int) $row['generation'];
    $record->httpStatus = null === $row['http_status'] ? null : (int) $row['http_status'];
    $record->lastError = null === $row['last_error'] ? null : (string) $row['last_error'];
    $record->nextRetryAt = null === $row['next_retry_at'] ? null : new DateTimeImmutable((string) $row['next_retry_at']);
    $record->leaseUntil = null === $row['lease_until'] ? null : new DateTimeImmutable((string) $row['lease_until']);
    $record->deliveredAt = null === $row['delivered_at'] ? null : new DateTimeImmutable((string) $row['delivered_at']);
    $record->createdAt = new DateTimeImmutable((string) $row['created_at']);
    $record->updatedAt = new DateTimeImmutable((string) $row['updated_at']);

    return WebhookDeliveryMapper::toDomain($record);
  }
  // #endregion
}
