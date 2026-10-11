<?php

declare(strict_types=1);

namespace Webhook\Domain\Model\Delivery;

use DateTimeImmutable;
use Webhook\Domain\ValueObject\WebhookDeliveryStatus;

/** Last persisted attempt and lifecycle state, including historical partial failures. */
final readonly class RestoredWebhookDeliveryAttempt
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries the last persisted delivery attempt and retry lifecycle state.
   *
   * @access public
   *
   * @param WebhookDeliveryStatus $status current delivery status
   * @param int $attempts number of delivery attempts made
   * @param ?int $httpStatus optional HTTP response status from the last attempt
   * @param ?string $lastError optional error recorded for the last attempt
   * @param ?DateTimeImmutable $nextRetryAt optional time scheduled for the next retry
   * @param ?DateTimeImmutable $deliveredAt optional time when delivery succeeded
   * @param int $generation durable manual-redelivery generation, 0 for historical rows
   * @param ?DateTimeImmutable $leaseUntil currently owned HTTP lease deadline
   * @param bool $quarantined whether automatic recovery is disabled
   *
   * @return void
   */
  public function __construct(
    public WebhookDeliveryStatus $status,
    public int $attempts,
    public ?int $httpStatus,
    public ?string $lastError,
    public ?DateTimeImmutable $nextRetryAt,
    public ?DateTimeImmutable $deliveredAt,
    public int $generation = 0,
    public ?DateTimeImmutable $leaseUntil = null,
    public bool $quarantined = false,
  ) {
  }
  // #endregion
}
