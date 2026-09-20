<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Workload\Application\Contract\Capacity\CapacityConfigurationView;

/**
 * CapacityOutput.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityOutput
{
  /**
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param CapacityConfigurationView $configuration historical capacity configuration for the requested scope
   */
  public function __construct(#[ApiProperty(identifier: true)] public string $organizationId, public CapacityConfigurationView $configuration)
  {
  }
}
