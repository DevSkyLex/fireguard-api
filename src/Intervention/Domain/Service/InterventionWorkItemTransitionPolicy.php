<?php

declare(strict_types=1);

namespace Intervention\Domain\Service;

use Intervention\Domain\Exception\{InterventionConflictException, InterventionValidationException};
use Intervention\Domain\ValueObject\InterventionWorkItemStatus;

use function in_array;
use function sprintf;
use function trim;

/**
 * Service InterventionWorkItemTransitionPolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkItemTransitionPolicy
{
  /**
   * Method assertAllowed.
   *
   * Executes the assert allowed operation. `skipped` additionally requires a
   * non-empty reason — the fact alone does not say why the required action
   * was not performed.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkItemStatus $from the from value
   * @param InterventionWorkItemStatus $to the to value
   * @param ?string $skipReason the skip reason value, required when $to is `skipped`
   */
  public function assertAllowed(InterventionWorkItemStatus $from, InterventionWorkItemStatus $to, ?string $skipReason = null): void
  {
    if ($from === $to) {
      return;
    }

    if (!in_array($to, $this->allowedFrom($from), true)) {
      throw new InterventionConflictException(sprintf('Work item cannot transition from %s to %s.', $from->value, $to->value));
    }

    if (InterventionWorkItemStatus::SKIPPED === $to && (null === $skipReason || '' === trim($skipReason))) {
      throw new InterventionValidationException('A skip reason is required.');
    }
  }

  /**
   * Method allowedFrom.
   *
   * Lists the workflow-legal next statuses from the given status. The
   * explicit returns are deliberate, to preserve the deployed frontend's
   * flows.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkItemStatus $from the from value
   *
   * @return list<InterventionWorkItemStatus> the allowed next statuses
   */
  public function allowedFrom(InterventionWorkItemStatus $from): array
  {
    return match ($from) {
      InterventionWorkItemStatus::PLANNED => [InterventionWorkItemStatus::IN_PROGRESS, InterventionWorkItemStatus::COMPLETED, InterventionWorkItemStatus::SKIPPED],
      InterventionWorkItemStatus::IN_PROGRESS => [InterventionWorkItemStatus::COMPLETED, InterventionWorkItemStatus::SKIPPED, InterventionWorkItemStatus::PLANNED],
      InterventionWorkItemStatus::COMPLETED => [InterventionWorkItemStatus::IN_PROGRESS],
      InterventionWorkItemStatus::SKIPPED => [InterventionWorkItemStatus::PLANNED],
    };
  }
}
