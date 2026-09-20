<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Capacity\GetCapacity;

use Shared\Application\Message\ResultMessage;
use Workload\Application\Contract\Capacity\CapacityConfigurationView;

/**
 * GetCapacityResult.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCapacityResult implements ResultMessage
{
  /**
   * @since 1.0.0
   *
   * @param CapacityConfigurationView $configuration historical capacity configuration for the requested scope
   */
  public function __construct(public CapacityConfigurationView $configuration)
  {
  }
}
