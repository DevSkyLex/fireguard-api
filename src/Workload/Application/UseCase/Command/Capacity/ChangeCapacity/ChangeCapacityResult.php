<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Command\Capacity\ChangeCapacity;

use Shared\Application\Message\ResultMessage;

/**
 * ChangeCapacityResult.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeCapacityResult implements ResultMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   */
  public function __construct(public string $id)
  {
  }
}
