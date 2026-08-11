<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\LeaveOrganization;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase LeaveOrganizationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LeaveOrganizationResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the LeaveOrganizationResult class.
   *
   * @since 1.0.0
   *
   * @param string $memberId the membership identifier that was deactivated
   * @param string $organizationId the organization identifier
   */
  public function __construct(
    public string $memberId,
    public string $organizationId,
  ) {
  }
  // #endregion
}
