<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateMissionChangeInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateMissionChangeInput
{
  /**
   * Property mission.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $mission = '';

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
