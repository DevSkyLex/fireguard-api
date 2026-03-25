<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase RemoveOrganizationMemberResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationMemberResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveOrganizationMemberResult class.
   *
   * @since 1.0.0
   *
   * @param string $memberId the member identifier
   * @param string $organizationId the organization identifier
   */
  public function __construct(
    public string $memberId,
    public string $organizationId,
  ) {
  }
  // #endregion
}
