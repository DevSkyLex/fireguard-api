<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * CapacityWeekInput.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CapacityWeekInput
{
  #[Assert\NotBlank]
  #[Assert\Date]
  public string $effectiveOn = '';

  /**
   * @var list<int>
   */
  #[Assert\Count(exactly: 7)]
  #[Assert\All([new Assert\Type('integer'), new Assert\Range(min: 0, max: 1440)])]
  public array $minutes = [];
}
