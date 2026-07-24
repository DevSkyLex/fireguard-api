<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO PostMessageInput.
 *
 * `POST /api/conversations/{conversationId}/messages` request body.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PostMessageInput
{
  /**
   * Property body.
   *
   * Rich-text body, sanitized server-side before persistence.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 40000)]
  public string $body = '';

  /**
   * Property references.
   *
   * Optional structured `{type, id, label?, code?}` rich-card references
   * (B3), at most `MessageReference::MAX_REFERENCES` entries.
   *
   * @since 1.3.0
   *
   * @var list<MessageReferenceInput>
   */
  #[Assert\Valid]
  #[Assert\Count(max: 5)]
  public array $references = [];
}
