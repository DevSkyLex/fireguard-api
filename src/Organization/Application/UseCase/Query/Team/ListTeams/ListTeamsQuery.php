<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\ListTeams;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListTeamsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTeamsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListTeamsQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
