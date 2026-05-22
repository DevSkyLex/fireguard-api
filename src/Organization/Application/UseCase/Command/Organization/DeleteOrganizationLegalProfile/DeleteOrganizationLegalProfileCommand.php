<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganizationLegalProfile;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteOrganizationLegalProfileCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationLegalProfileCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteOrganizationLegalProfileCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
