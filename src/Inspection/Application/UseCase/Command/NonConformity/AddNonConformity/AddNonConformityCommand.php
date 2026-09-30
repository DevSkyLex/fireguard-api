<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\AddNonConformity;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddNonConformityCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddNonConformityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the inspection finding and its optional due date and notes for non-conformity creation.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the inspection
   * @param string $inspectionId inspection where the finding was recorded
   * @param string $description description of the observed deficiency
   * @param string $severity severity assigned to the finding
   * @param ?string $dueAt optional resolution deadline
   * @param ?string $notes optional handling notes
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $description,
    public string $severity,
    public ?string $dueAt = null,
    public ?string $notes = null,
  ) {
  }
  // #endregion
}
