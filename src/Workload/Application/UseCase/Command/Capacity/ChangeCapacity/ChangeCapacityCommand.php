<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Command\Capacity\ChangeCapacity;

use Shared\Application\Message\CommandMessage;

/**
 * ChangeCapacityCommand.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeCapacityCommand implements CommandMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier used for authorization
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $kind requested capacity operation
   * @param ?string $memberId member scope; null selects organization defaults for weekly capacity
   * @param ?string $effectiveOn first local date on which this capacity version applies
   * @param list<int> $weekMinutes
   * @param ?string $startsOn inclusive organization-local start date
   * @param ?string $endsOn inclusive organization-local end date
   * @param int $minutes duration in whole minutes
   * @param ?string $exceptionId availability exception to cancel without erasing history
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $kind,
    public ?string $memberId = null,
    public ?string $effectiveOn = null,
    public array $weekMinutes = [],
    public ?string $startsOn = null,
    public ?string $endsOn = null,
    public int $minutes = 0,
    public ?string $exceptionId = null,
  ) {
  }
}
