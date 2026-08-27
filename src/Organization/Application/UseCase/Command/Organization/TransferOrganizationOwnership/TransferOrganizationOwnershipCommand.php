<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase TransferOrganizationOwnershipCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TransferOrganizationOwnershipCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TransferOrganizationOwnershipCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $actingUserId the user ID performing the transfer; must be
   *                             the organization's current owner
   * @param string $newOwnerUserId the user ID of the new owner; must be an
   *                               active member of the organization
   * @param ?string $slugConfirmation the organization slug as typed by the
   *                                  caller, required to match the
   *                                  organization's current slug
   *                                  (danger-zone confirmation guard)
   */
  public function __construct(
    public string $organizationId,
    public string $actingUserId,
    public string $newOwnerUserId,
    public ?string $slugConfirmation = null,
  ) {
  }
  // #endregion
}
