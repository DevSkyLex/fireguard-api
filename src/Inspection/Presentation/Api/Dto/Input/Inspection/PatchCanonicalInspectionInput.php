<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Inspection;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO PatchCanonicalInspectionInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PatchCanonicalInspectionInput
{
  /**
   * Property result.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['pass', 'fail', 'partial'])]
  public ?string $result = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['draft', 'submitted', 'closed'])]
  public ?string $status = null;

  /**
   * Property notes.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 5000)]
  public ?string $notes = null;

  /**
   * Property signature.
   *
   * @since 1.0.0
   */
  public ?string $signature = null;
}
