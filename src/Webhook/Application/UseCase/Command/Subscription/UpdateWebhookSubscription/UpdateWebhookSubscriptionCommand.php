<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateWebhookSubscriptionCommand.
 *
 * A PATCH-style partial update: `null` means "leave unchanged" for every
 * field except `description`, which may be cleared with an empty string.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateWebhookSubscriptionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the acting user identifier
   * @param string $subscriptionId the webhook subscription identifier
   * @param ?string $url the new target URL, when changing
   * @param ?list<string> $eventTypes the new subscribed public event type allowlist, when changing
   * @param ?bool $isActive the new active flag, when changing
   * @param ?string $description the new description, when changing
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $subscriptionId,
    public ?string $url = null,
    public ?array $eventTypes = null,
    public ?bool $isActive = null,
    public ?string $description = null,
  ) {
  }
  // #endregion
}
