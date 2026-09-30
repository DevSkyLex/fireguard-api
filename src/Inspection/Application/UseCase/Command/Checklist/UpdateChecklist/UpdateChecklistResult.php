<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\UpdateChecklist;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateChecklistResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateChecklistResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the updated checklist identity, organization scope, and modification time.
   *
   * @access public
   *
   * @param string $checklistId identifier of the updated checklist
   * @param string $organizationId organization owning the checklist
   * @param DateTimeImmutable $updatedAt time the checklist was updated
   *
   * @return void
   */
  public function __construct(
    public string $checklistId,
    public string $organizationId,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
