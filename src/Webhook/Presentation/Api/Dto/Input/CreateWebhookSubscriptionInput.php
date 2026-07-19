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
 * DTO CreateWebhookSubscriptionInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateWebhookSubscriptionInput
{
  // #region Properties
  /**
   * Property url.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'A target URL is required.')]
  #[ValidWebhookUrl]
  #[Groups([WebhookSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Target URL deliveries are POSTed to', required: true, example: 'https://example.com/webhooks/fireguard')]
  public string $url = '';

  /**
   * Property eventTypes.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\Count(min: 1, minMessage: 'At least one event type is required.')]
  #[Assert\All([
    new Assert\Choice(callback: [WebhookEventCatalog::class, 'allowedEventTypes']),
  ])]
  #[Groups([WebhookSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Subscribed public event type allowlist', required: true, example: ['intervention.published'])]
  public array $eventTypes = [];

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
