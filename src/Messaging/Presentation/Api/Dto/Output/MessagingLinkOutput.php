<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO MessagingLinkOutput.
 *
 * A URL extracted from a message body (B2), as shown in the conversation
 * Links tab (`GET /conversations/{conversationId}/links`).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingLinkOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property url.
   *
   * @since 1.0.0
   */
  public string $url = '';

  /**
   * Property label.
   *
   * Never populated by extraction today — reserved for a future
   * link-preview feature.
   *
   * @since 1.0.0
   */
  public ?string $label = null;

  /**
   * Property messageId.
   *
   * The owning message's identifier.
   *
   * @since 1.0.0
   */
  public string $messageId = '';

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';
}
