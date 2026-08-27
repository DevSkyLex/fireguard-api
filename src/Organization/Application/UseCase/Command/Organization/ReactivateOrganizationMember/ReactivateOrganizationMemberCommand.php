<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ReactivateOrganizationMember;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ReactivateOrganizationMemberCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReactivateOrganizationMemberCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ReactivateOrganizationMemberCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the member identifier to reactivate
   */
  public function __construct(
    public string $organizationId,
    public string $memberId,
  ) {
  }
  // #endregion
}
