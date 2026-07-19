<?php

declare(strict_types=1);

namespace Webhook\Domain\Model\Delivery;

use DateTimeImmutable;
use InvalidArgumentException;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};

/**
 * Model WebhookDelivery.
 *
 * One outbound delivery attempt log row for a single (subscription, source
 * event) pair — the `(subscription_id, event_id)` uniqueness is enforced at
 * the reservation site ({@see \Webhook\Infrastructure\Persistence\Doctrine\Repository\WebhookDeliveryRepository::reserve()}),
 * mirroring `Import\Domain\Model\ImportJob\ImportJob`'s lifecycle style.
 *
 * State machine: `pending` -> (`delivered` | `failed`). `pending` may be
 * re-attempted any number of times (each attempt increments `attempts` and
 * may refresh `nextRetryAt`) until either a 2xx response marks it
 * `delivered`, or the maximum attempt count is exhausted and it is marked
 * `failed` (terminal).
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookDelivery
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookDeliveryId $id the delivery identifier
   * @param WebhookSubscriptionId $subscriptionId the target subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $eventType the public event type delivered
   * @param string $eventId the source domain event identifier (dedupe key)
   * @param array<string, mixed> $payload the rendered public payload envelope
   * @param WebhookDeliveryStatus $status the current lifecycle status
   * @param int $attempts the number of delivery attempts made so far
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?int $httpStatus the HTTP status of the most recent attempt
   * @param ?string $lastError the most recent failure reason
   * @param ?DateTimeImmutable $nextRetryAt when the next retry is due
   * @param ?DateTimeImmutable $deliveredAt when the delivery succeeded
   */
  private function __construct(
    private WebhookDeliveryId $id,
    private WebhookSubscriptionId $subscriptionId,
    private string $organizationId,
    private string $eventType,
    private string $eventId,
    private array $payload,
    private WebhookDeliveryStatus $status,
    private int $attempts,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?int $httpStatus = null,
    private ?string $lastError = null,
    private ?DateTimeImmutable $nextRetryAt = null,
    private ?DateTimeImmutable $deliveredAt = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new pending webhook delivery.
   *
   * @since 1.0.0
   *
   * @param WebhookDeliveryId $id the delivery identifier
   * @param WebhookSubscriptionId $subscriptionId the target subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $eventType the public event type delivered
   * @param string $eventId the source domain event identifier (dedupe key)
   * @param array<string, mixed> $payload the rendered public payload envelope
   *
   * @return self the created delivery
   */
  public static function create(
    WebhookDeliveryId $id,
    WebhookSubscriptionId $subscriptionId,
    string $organizationId,
    string $eventType,
    string $eventId,
    array $payload,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      subscriptionId: $subscriptionId,
      organizationId: $organizationId,
      eventType: $eventType,
      eventId: $eventId,
      payload: $payload,
      status: WebhookDeliveryStatus::PENDING,
      attempts: 0,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes a webhook delivery aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param WebhookDeliveryId $id the delivery identifier
   * @param WebhookSubscriptionId $subscriptionId the target subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $eventType the public event type delivered
   * @param string $eventId the source domain event identifier
   * @param array<string, mixed> $payload the rendered public payload envelope
   * @param WebhookDeliveryStatus $status the current lifecycle status
   * @param int $attempts the number of delivery attempts made so far
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?int $httpStatus the HTTP status of the most recent attempt
   * @param ?string $lastError the most recent failure reason
   * @param ?DateTimeImmutable $nextRetryAt when the next retry is due
   * @param ?DateTimeImmutable $deliveredAt when the delivery succeeded
   *
   * @return self the reconstituted delivery
   */
  public static function reconstitute(
    WebhookDeliveryId $id,
    WebhookSubscriptionId $subscriptionId,
    string $organizationId,
    string $eventType,
    string $eventId,
    array $payload,
    WebhookDeliveryStatus $status,
    int $attempts,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?int $httpStatus,
    ?string $lastError,
    ?DateTimeImmutable $nextRetryAt,
    ?DateTimeImmutable $deliveredAt,
  ): self {
    return new self(
      id: $id,
      subscriptionId: $subscriptionId,
      organizationId: $organizationId,
      eventType: $eventType,
      eventId: $eventId,
      payload: $payload,
      status: $status,
      attempts: $attempts,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      httpStatus: $httpStatus,
      lastError: $lastError,
      nextRetryAt: $nextRetryAt,
      deliveredAt: $deliveredAt,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): WebhookDeliveryId
  {
    return $this->id;
  }

  /**
   * Method subscriptionId.
   *
   * @since 1.0.0
   */
  public function subscriptionId(): WebhookSubscriptionId
  {
    return $this->subscriptionId;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method eventType.
   *
   * @since 1.0.0
   */
  public function eventType(): string
  {
    return $this->eventType;
  }

  /**
   * Method eventId.
   *
   * @since 1.0.0
   */
  public function eventId(): string
  {
    return $this->eventId;
  }

  /**
   * Method payload.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the rendered public payload envelope
   */
  public function payload(): array
  {
    return $this->payload;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): WebhookDeliveryStatus
  {
    return $this->status;
  }

  /**
   * Method attempts.
   *
   * @since 1.0.0
   */
  public function attempts(): int
  {
    return $this->attempts;
  }

  /**
   * Method httpStatus.
   *
   * @since 1.0.0
   */
  public function httpStatus(): ?int
  {
    return $this->httpStatus;
  }

  /**
   * Method lastError.
   *
   * @since 1.0.0
   */
  public function lastError(): ?string
  {
    return $this->lastError;
  }

  /**
   * Method nextRetryAt.
   *
   * @since 1.0.0
   */
  public function nextRetryAt(): ?DateTimeImmutable
  {
    return $this->nextRetryAt;
  }

  /**
   * Method deliveredAt.
   *
   * @since 1.0.0
   */
  public function deliveredAt(): ?DateTimeImmutable
  {
    return $this->deliveredAt;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method markDelivered.
   *
   * Records a successful (2xx) delivery attempt. Idempotent: a delivery
   * already marked `delivered` is left untouched (a Messenger redelivery
   * of an already-succeeded job is a routine, safe no-op).
   *
   * @since 1.0.0
   *
   * @param int $httpStatus the successful HTTP status code
   * @param DateTimeImmutable $now the current time
   */
  public function markDelivered(int $httpStatus, DateTimeImmutable $now): void
  {
    if (WebhookDeliveryStatus::DELIVERED === $this->status) {
      return;
    }

    ++$this->attempts;
    $this->status = WebhookDeliveryStatus::DELIVERED;
    $this->httpStatus = $httpStatus;
    $this->lastError = null;
    $this->nextRetryAt = null;
    $this->deliveredAt = $now;
    $this->touch($now);
  }

  /**
   * Method recordRetryableFailure.
   *
   * Records a failed attempt that has not yet exhausted the maximum
   * attempt count: the delivery stays `pending`, awaiting Messenger's
   * retry/backoff to redeliver the message.
   *
   * @since 1.0.0
   *
   * @param ?int $httpStatus the HTTP status of the failed attempt, when one was received
   * @param string $error the failure reason
   * @param ?DateTimeImmutable $nextRetryAt when the next retry is expected, when known
   * @param DateTimeImmutable $now the current time
   */
  public function recordRetryableFailure(?int $httpStatus, string $error, ?DateTimeImmutable $nextRetryAt, DateTimeImmutable $now): void
  {
    $this->assertPending();

    ++$this->attempts;
    $this->httpStatus = $httpStatus;
    $this->lastError = $error;
    $this->nextRetryAt = $nextRetryAt;
    $this->touch($now);
  }

  /**
   * Method markFailed.
   *
   * Marks the delivery terminally failed: the maximum attempt count has
   * been exhausted.
   *
   * @since 1.0.0
   *
   * @param ?int $httpStatus the HTTP status of the last attempt, when one was received
   * @param string $error the terminal failure reason
   * @param DateTimeImmutable $now the current time
   */
  public function markFailed(?int $httpStatus, string $error, DateTimeImmutable $now): void
  {
    $this->assertPending();

    ++$this->attempts;
    $this->status = WebhookDeliveryStatus::FAILED;
    $this->httpStatus = $httpStatus;
    $this->lastError = $error;
    $this->nextRetryAt = null;
    $this->touch($now);
  }

  /**
   * Method reopen.
   *
   * Reopens a terminally `failed` delivery back to `pending` for a manual
   * redelivery attempt, resetting the attempt counter.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   */
  public function reopen(DateTimeImmutable $now): void
  {
    $this->status = WebhookDeliveryStatus::PENDING;
    $this->attempts = 0;
    $this->httpStatus = null;
    $this->lastError = null;
    $this->nextRetryAt = null;
    $this->touch($now);
  }

  /**
   * Method assertPending.
   *
   * @since 1.0.0
   */
  private function assertPending(): void
  {
    if (WebhookDeliveryStatus::PENDING !== $this->status) {
      throw new InvalidArgumentException('A terminal webhook delivery cannot record another attempt.');
    }
  }

  /**
   * Method touch.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   */
  private function touch(DateTimeImmutable $now): void
  {
    $this->updatedAt = $now;
  }
  // #endregion
}
