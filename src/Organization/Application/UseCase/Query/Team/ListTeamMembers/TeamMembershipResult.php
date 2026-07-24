<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\ListTeamMembers;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase TeamMembershipResult.
 *
 * Read model for a single team membership row.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamMembershipResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the TeamMembershipResult class.
   *
   * @since 1.0.0
   *
   * @param string $memberId the organization member identifier
   * @param ?string $role the free-form membership label, when set
   * @param DateTimeImmutable $addedAt the membership creation timestamp
   */
  public function __construct(
    public string $memberId,
    public ?string $role,
    public DateTimeImmutable $addedAt,
  ) {
  }
  // #endregion
}
