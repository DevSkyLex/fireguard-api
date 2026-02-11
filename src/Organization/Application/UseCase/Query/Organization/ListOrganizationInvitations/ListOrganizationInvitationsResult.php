<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListOrganizationInvitationsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationInvitationsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListOrganizationInvitationsResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetOrganizationInvitationResult> $invitations the organization invitations
   */
  public function __construct(
    public array $invitations,
  ) {
  }
  // #endregion
}
