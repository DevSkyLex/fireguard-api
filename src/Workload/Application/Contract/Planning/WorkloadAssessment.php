<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Planning;

/**
 * WorkloadAssessment.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadAssessment
{
  /**
   * @since 1.0.0
   *
   * @param bool $confirmationRequired whether the proposal creates or increases daily overload
   * @param list<array{memberId: string, memberName?: string, date: ?string, reason: string, beforeMinutes: int, afterMinutes: int, capacityMinutes: ?int}> $increases
   * @param string $completeness calculation quality: complete, partial, or unavailable
   * @param string $confirmationToken consent bound to the exact server assessment, never blanket approval
   */
  public function __construct(public bool $confirmationRequired, public array $increases, public string $completeness, public string $confirmationToken)
  {
  }
}
