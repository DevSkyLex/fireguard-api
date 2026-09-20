<?php

declare(strict_types=1);

namespace Workload\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function in_array;

/**
 * Value object WorkDemand: normalized remaining task contribution.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkDemand
{
  /**
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param ?string $memberId assigned member, or null for unassigned demand
   * @param ?int $remainingMinutes explicit remaining effort in whole minutes; never derived from actual time
   * @param ?LocalDate $startsOn inclusive organization-local start date
   * @param ?LocalDate $endsOn inclusive organization-local end date
   * @param string $commitment demand classification: draft, committed, or no future demand
   */
  public function __construct(
    public string $taskId,
    public ?string $memberId,
    public ?int $remainingMinutes,
    public ?LocalDate $startsOn,
    public ?LocalDate $endsOn,
    public string $commitment,
  ) {
    if (!in_array($commitment, ['committed', 'draft', 'none'], true) || (null !== $remainingMinutes && $remainingMinutes < 0)) {
      throw InvalidValueException::because('Invalid normalized work demand.');
    }
    if (null !== $startsOn && null !== $endsOn && $startsOn->value > $endsOn->value) {
      throw InvalidValueException::because('Invalid task period.');
    }
  }
}
