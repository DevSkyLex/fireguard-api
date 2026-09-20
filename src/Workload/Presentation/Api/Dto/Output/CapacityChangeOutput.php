<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * CapacityChangeOutput.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityChangeOutput
{
  /**
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   */
  public function __construct(#[ApiProperty(identifier: true)] public string $id)
  {
  }
}
