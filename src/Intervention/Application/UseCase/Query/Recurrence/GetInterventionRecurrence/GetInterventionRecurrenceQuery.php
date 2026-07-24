<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Recurrence\GetInterventionRecurrence;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInterventionRecurrenceQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionRecurrenceQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $recurrenceId the recurrence id value
   */
  public function __construct(
    public string $userId,
    public string $recurrenceId,
  ) {
  }
}
