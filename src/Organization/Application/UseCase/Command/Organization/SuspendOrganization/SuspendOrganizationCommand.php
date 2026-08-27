<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\SuspendOrganization;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SuspendOrganizationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SuspendOrganizationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SuspendOrganizationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $actingUserId the user identifier performing the suspension
   */
  public function __construct(
    public string $organizationId,
    public string $actingUserId,
  ) {
  }
  // #endregion
}
