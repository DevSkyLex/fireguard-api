<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreatePublicationInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreatePublicationInput
{
  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $intervention = '';

  /**
   * Property interventionRevision.
   *
   * @since 1.0.0
   */
  #[Assert\Positive]
  public int $interventionRevision = 0;
}
