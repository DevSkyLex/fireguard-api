<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListUserOrganizationsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserOrganizationsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListUserOrganizationsResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetOrganizationResult> $organizations the user organizations
   */
  public function __construct(
    public array $organizations,
  ) {
  }
  // #endregion
}
