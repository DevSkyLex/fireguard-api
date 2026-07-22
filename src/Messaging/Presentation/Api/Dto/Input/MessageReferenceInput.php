<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO MessageReferenceInput.
 *
 * A single `{type, id, label?, code?}` structured reference (B3), embedded
 * in `PostMessageInput`/`EditMessageInput`'s `references` list. Shape
 * validation only (type whitelist, non-blank id, bounded lengths) — the
 * command handler additionally validates org-scoped EXISTENCE through the
 * `messaging.subject_resolver` seam.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageReferenceInput
{
  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Choice(choices: ['non_conformity', 'intervention', 'facility', 'equipment'])]
  public string $type = '';

  /**
   * Property id.
   *
   * The referenced record's bare identifier (not an IRI).
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 36)]
  public string $id = '';

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  public ?string $label = null;

  /**
   * Property code.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 64)]
  public ?string $code = null;
}
