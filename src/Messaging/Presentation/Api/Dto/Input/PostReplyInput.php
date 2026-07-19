<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO PostReplyInput.
 *
 * `POST /api/messages/{id}/replies` request body (L2.5).
 *
 * @category DTO
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PostReplyInput
{
  /**
   * Property body.
   *
   * Rich-text body, sanitized server-side before persistence.
   *
   * @since 1.2.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 40000)]
  public string $body = '';
}
