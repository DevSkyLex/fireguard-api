<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AddReactionInput.
 *
 * `POST /api/messages/{id}/reactions` request body. This is a shallow,
 * defense-in-depth gate (non-blank, bounded length) — the authoritative
 * "is this a plausible single emoji grapheme" check lives in the domain
 * value object `Messaging\Domain\ValueObject\MessagingEmoji`, which the
 * command handler always validates against regardless of this DTO.
 *
 * @category DTO
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AddReactionInput
{
  /**
   * Property emoji.
   *
   * The reaction emoji.
   *
   * @since 1.1.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 32)]
  public string $emoji = '';
}
