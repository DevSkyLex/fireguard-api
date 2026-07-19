<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\DeleteInterventionRecurrence;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteInterventionRecurrenceCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionRecurrenceCommand implements CommandMessage
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
