<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Capacity\GetCapacity;

use Shared\Application\Message\QueryMessage;

/**
 * GetCapacityQuery.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCapacityQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier used for authorization
   * @param string $organizationId organization identifier that scopes this operation
   * @param ?string $memberId member whose capacity is requested; null selects organization defaults
   */
  public function __construct(public string $userId, public string $organizationId, public ?string $memberId = null)
  {
  }
}
