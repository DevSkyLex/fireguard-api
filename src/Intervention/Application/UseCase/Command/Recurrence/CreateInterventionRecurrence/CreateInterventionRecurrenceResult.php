<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\CreateInterventionRecurrence;

use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateInterventionRecurrenceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionRecurrenceResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $recurrence the created recurrence view
   */
  public function __construct(
    public InterventionRecurrenceView $recurrence,
  ) {
  }
}
