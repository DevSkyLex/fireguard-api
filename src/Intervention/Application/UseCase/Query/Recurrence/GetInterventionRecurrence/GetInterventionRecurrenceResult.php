<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Recurrence\GetInterventionRecurrence;

use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetInterventionRecurrenceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionRecurrenceResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $recurrence the recurrence view
   */
  public function __construct(
    public InterventionRecurrenceView $recurrence,
  ) {
  }
}
