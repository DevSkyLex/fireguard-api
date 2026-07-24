<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganization;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteOrganizationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteOrganizationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param ?string $slugConfirmation the organization slug as typed by the caller,
   *                                  required to match the organization's current
   *                                  slug (danger-zone confirmation guard)
   */
  public function __construct(
    public string $organizationId,
    public ?string $slugConfirmation = null,
  ) {
  }
  // #endregion
}
