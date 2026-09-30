<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateNonConformityStatusCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateNonConformityStatusCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped inspection and non-conformity status transition.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the transition
   * @param string $inspectionId inspection containing the finding
   * @param string $nonConformityId finding whose status changes
   * @param string $status requested target status
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $nonConformityId,
    public string $status,
  ) {
  }
  // #endregion
}
