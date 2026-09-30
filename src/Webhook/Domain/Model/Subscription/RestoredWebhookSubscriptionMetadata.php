<?php

declare(strict_types=1);

namespace Webhook\Domain\Model\Subscription;

use DateTimeImmutable;

/**
 * Persisted descriptive and lifecycle fields of a webhook subscription.
 */
final readonly class RestoredWebhookSubscriptionMetadata
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries descriptive metadata and timestamps for a webhook subscription.
   *
   * @access public
   *
   * @param string $description subscription description
   * @param DateTimeImmutable $createdAt subscription creation timestamp
   * @param DateTimeImmutable $updatedAt most recent subscription update timestamp
   *
   * @return void
   */
  public function __construct(
    public string $description,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
