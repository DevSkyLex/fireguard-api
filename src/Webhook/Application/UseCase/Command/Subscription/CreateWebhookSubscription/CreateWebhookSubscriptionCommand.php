<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateWebhookSubscriptionCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateWebhookSubscriptionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the acting user identifier
   * @param string $url the target URL deliveries are POSTed to
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param ?string $description the free-form description
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $url,
    public array $eventTypes,
    public ?string $description = null,
  ) {
  }
  // #endregion
}
