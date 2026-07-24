<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListOrganizationNonConformitiesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationNonConformitiesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<OrganizationNonConformityResult> $nonConformities the non-conformity list
   */
  public function __construct(
    public array $nonConformities,
  ) {
  }
  // #endregion
}
