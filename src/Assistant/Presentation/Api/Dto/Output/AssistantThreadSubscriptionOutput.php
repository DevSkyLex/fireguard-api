<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO AssistantThreadSubscriptionOutput.
 *
 * Carries the Mercure subscriber JWT and the exact private topic
 * (`/organizations/{organizationId}/assistant/threads/{threadId}`) the
 * client must subscribe to. Never a wildcard, so the JWT can never read
 * another thread's generation stream (mirrors
 * `Messaging\Presentation\Api\Dto\Output\MessagingSubscriptionOutput`).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantThreadSubscriptionOutput
{
  // #region Properties
  /**
   * Property token.
   *
   * Mercure subscriber JWT.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $token = '';

  /**
   * Property topic.
   *
   * The exact Mercure topic to subscribe to.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $topic = '';
  // #endregion
}
