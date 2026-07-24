<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase RotateWebhookSecretResult.
 *
 * Mirrors `CreateWebhookSubscriptionResult`'s shape (full subscription view
 * + secret) so both map to the same `WebhookSecretOutput` Presentation DTO —
 * `secret` carries the new PLAINTEXT signing secret, returned only once.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RotateWebhookSecretResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $url the target URL
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param bool $isActive whether deliveries are currently enqueued
   * @param string $description the free-form description
   * @param string $secret the new plaintext signing secret (once)
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $url,
    public array $eventTypes,
    public bool $isActive,
    public string $description,
    public string $secret,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
