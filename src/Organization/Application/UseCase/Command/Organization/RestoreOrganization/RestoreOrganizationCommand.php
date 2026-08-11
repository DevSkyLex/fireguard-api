<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RestoreOrganization;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RestoreOrganizationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoreOrganizationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RestoreOrganizationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $actingUserId the user identifier performing the restoration
   */
  public function __construct(
    public string $organizationId,
    public string $actingUserId,
  ) {
  }
  // #endregion
}
