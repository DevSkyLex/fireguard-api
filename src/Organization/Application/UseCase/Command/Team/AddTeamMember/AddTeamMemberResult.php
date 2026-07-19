<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\AddTeamMember;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddTeamMemberResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddTeamMemberResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AddTeamMemberResult class.
   *
   * @since 1.0.0
   *
   * @param string $teamId the team identifier
   * @param string $memberId the organization member identifier
   * @param ?string $role the free-form membership label, when set
   * @param DateTimeImmutable $addedAt the membership creation timestamp
   */
  public function __construct(
    public string $teamId,
    public string $memberId,
    public ?string $role,
    public DateTimeImmutable $addedAt,
  ) {
  }
  // #endregion
}
