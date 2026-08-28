<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\SearchOrganization;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase SearchOrganizationQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SearchOrganizationQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the requesting user identifier
   * @param string $term the free-text search term
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public string $term,
  ) {
  }
  // #endregion
}
