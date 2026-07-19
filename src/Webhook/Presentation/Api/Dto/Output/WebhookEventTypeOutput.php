<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Webhook\Presentation\Api\Serialization\WebhookSerializationGroup;

/**
 * DTO WebhookEventTypeOutput.
 *
 * Reference-catalog row for `GET /webhooks/event-types` (ARCHITECTURE.md's
 * "Reference catalog" category — the frontend must not hard-code the
 * curated allowlist, and the value list may evolve without a redeploy).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookEventTypeOutput
{
  // #region Properties
  /**
   * Property value.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $value = '';

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';
  // #endregion
}
