<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\RecoverReservedRecurrence;

use Shared\Application\Message\CommandMessage;

/**
 * Class RecoverReservedRecurrenceCommand
 *
 * Resolves an ambiguous legacy occurrence using an operator-confirmed draft or failure.
 *
 * @category Command
 */
final readonly class RecoverReservedRecurrenceCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $runId unresolved run identifier
   * @param ?string $interventionId existing complete draft verified by the operator
   * @param ?string $failureReason explicit reason when the occurrence must be skipped
   *
   * @return void
   */
  public function __construct(public string $runId, public ?string $interventionId = null, public ?string $failureReason = null)
  {
  }
  // #endregion
}
