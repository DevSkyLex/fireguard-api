<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\GetInspection;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInspectionQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInspectionQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies an inspection within its organization for retrieval.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the read
   * @param string $inspectionId inspection to retrieve
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
