<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Webhook\Presentation\Api\Serialization\WebhookSerializationGroup;

/**
 * DTO WebhookSecretOutput.
 *
 * Returned ONLY by `POST /webhooks/subscriptions` (create) and
 * `POST /webhooks/subscriptions/{id}/rotate-secret` — the one and only time
 * the plaintext signing secret is ever exposed; every other read returns
 * `WebhookSubscriptionOutput` instead.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookSecretOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property url.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $url = '';

  /**
   * Property eventTypes.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $eventTypes = [];

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isActive = true;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $description = '';

  /**
   * Property secret.
   *
   * The plaintext HMAC signing secret — shown once, never persisted or
   * retrievable again.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $secret = '';

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
  // #endregion
}
