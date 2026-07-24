<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\UpdateInterventionRecurrence;

use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateInterventionRecurrenceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInterventionRecurrenceResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $recurrence the updated recurrence view
   */
  public function __construct(
    public InterventionRecurrenceView $recurrence,
  ) {
  }
}
