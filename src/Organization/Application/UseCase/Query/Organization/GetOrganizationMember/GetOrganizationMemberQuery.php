<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationMember;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationMemberQuery.
 *
 * Resolves a single organization membership by its identifier. Used by
 * read-side projections that need to translate a member reference into the
 * underlying user (e.g. embedding an assignee identity on a work item).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationMemberQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationMemberQuery class.
   *
   * @since 1.0.0
   *
   * @param string $memberId the organization member identifier
   */
  public function __construct(
    public string $memberId,
  ) {
  }
  // #endregion
}
