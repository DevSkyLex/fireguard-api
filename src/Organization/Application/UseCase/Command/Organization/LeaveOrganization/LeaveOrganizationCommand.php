<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\LeaveOrganization;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase LeaveOrganizationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LeaveOrganizationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the LeaveOrganizationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $actingUserId the user identifier of the member leaving
   *                             the organization
   */
  public function __construct(
    public string $organizationId,
    public string $actingUserId,
  ) {
  }
  // #endregion
}
