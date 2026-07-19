<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Webhook\Application\Contract\Event\WebhookEventCatalog;
use Webhook\Presentation\Api\Serialization\WebhookSerializationGroup;
use Webhook\Presentation\Api\Validator\ValidWebhookUrl\ValidWebhookUrl;

/**
 * DTO UpdateWebhookSubscriptionInput.
 *
 * A PATCH-style partial update: an omitted (`null`) property leaves the
 * corresponding field unchanged.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateWebhookSubscriptionInput
{
  // #region Properties
  /**
   * Property url.
   *
   * @since 1.0.0
   */
  #[ValidWebhookUrl]
  #[Groups([WebhookSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Target URL deliveries are POSTed to', required: false)]
  public ?string $url = null;

  /**
   * Property eventTypes.
   *
   * @since 1.0.0
   *
   * @var ?list<string>
   */
  #[Assert\All([
    new Assert\Choice(callback: [WebhookEventCatalog::class, 'allowedEventTypes']),
  ])]
  #[Groups([WebhookSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Subscribed public event type allowlist', required: false)]
  public ?array $eventTypes = null;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether deliveries are currently enqueued', required: false)]
  public ?bool $isActive = null;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 500, maxMessage: 'Description cannot exceed 500 characters.')]
  #[Groups([WebhookSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Free-form description', required: false)]
  public ?string $description = null;
  // #endregion
}
