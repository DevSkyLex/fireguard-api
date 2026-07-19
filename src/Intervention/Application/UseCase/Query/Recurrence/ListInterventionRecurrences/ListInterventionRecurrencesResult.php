<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Recurrence\ListInterventionRecurrences;

use Intervention\Application\Contract\Recurrence\InterventionRecurrencePage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInterventionRecurrencesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionRecurrencesResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrencePage $page the recurrence page value
   */
  public function __construct(
    public InterventionRecurrencePage $page,
  ) {
  }
}
