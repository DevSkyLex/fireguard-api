<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Contract\Work;

/**
 * Class ServiceRequestWorkLink
 *
 * Identifies real corrective work while preserving its existing execution evidence.
 *
 * @category Contract
 */
final readonly class ServiceRequestWorkLink
{
  public function __construct(public string $interventionId, public string $taskId, public bool $created)
  {
  }
}
