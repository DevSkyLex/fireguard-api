<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateInspectionResponseCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInspectionResponseCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the checklist response identifier, expected revision, and replacement answer value.
   *
   * @access public
   *
   * @param string $responseId response to update
   * @param int $expectedRevision revision read by the caller before submitting the change
   * @param mixed $value replacement answer value retained for the checklist item
   *
   * @return void
   */
  public function __construct(
    public string $responseId,
    public int $expectedRevision,
    public mixed $value = null,
  ) {
  }
  // #endregion
}
