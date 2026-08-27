<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationLogo;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RemoveOrganizationLogoCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationLogoCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveOrganizationLogoCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $actingUserId the user identifier performing the removal
   */
  public function __construct(
    public string $organizationId,
    public string $actingUserId,
  ) {
  }
  // #endregion
}
