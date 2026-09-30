<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * CapacityExceptionInput.
 *
 * @category Workload
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CapacityExceptionInput
{
  // #region Properties
  /**
   * Property startsOn
   */
  #[Assert\NotBlank]
  #[Assert\Date]
  public string $startsOn = '';

  /**
   * Property endsOn
   */
  #[Assert\NotBlank]
  #[Assert\Date]
  public string $endsOn = '';

  /**
   * Property minutes
   */
  #[Assert\Range(min: 0, max: 1440)]
  public int $minutes = 0;
  // #endregion
}
