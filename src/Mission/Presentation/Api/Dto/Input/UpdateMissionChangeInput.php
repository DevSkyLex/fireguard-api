<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateMissionChangeInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateMissionChangeInput
{
  /**
   * Property patch.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>|null
   */
  public ?array $patch = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['proposed', 'rejected'])]
  public ?string $status = null;
}
