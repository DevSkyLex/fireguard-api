<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInterventionChangeInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInterventionChangeInput
{
  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $intervention = '';

  /**
   * Property workItem.
   *
   * @since 1.0.0
   */
  public ?string $workItem = null;

  /**
   * Property resource.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $resource = '';

  /**
   * Property patch.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[Assert\NotBlank]
  public array $patch = [];
}
