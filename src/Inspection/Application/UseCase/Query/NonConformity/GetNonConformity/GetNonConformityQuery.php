<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformity;

use Shared\Application\Message\QueryMessage;

/**
 * Class GetNonConformityQuery
 *
 * Carries the criteria for the GetNonConformityQuery read operation.
 *
 * @category UseCase
 */
final readonly class GetNonConformityQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the GetNonConformityQuery dependencies and state.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $inspectionId the inspection identifier
   * @param string $nonConformityId the non conformity identifier
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $nonConformityId,
  ) {
  }
  // #endregion
}
