<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO MessagingSubscriptionOutput.
 *
 * Carries the Mercure subscriber JWT and the exact private topic
 * (`/organizations/{organizationId}/conversations/{conversationId}`) the
 * client must subscribe to. Never a wildcard, so the JWT can never read
 * another conversation's updates.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingSubscriptionOutput
{
  /**
   * Property token.
   *
   * Mercure subscriber JWT.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $token = '';

  /**
   * Property topic.
   *
   * The exact Mercure topic to subscribe to.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $topic = '';
}
