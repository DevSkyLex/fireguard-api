<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInterventionCommentInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInterventionCommentInput
{
  /**
   * Property body.
   *
   * Rich-text (HTML) comment body produced by the PrimeNG/Quill editor. It is
   * sanitized against a safe allowlist in the processor before persistence, so
   * the generous length bound accounts for markup overhead.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 20000)]
  public string $body = '';
}
