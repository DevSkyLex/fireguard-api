<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipment;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetEquipmentQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies an equipment item within its organization for retrieval.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the lookup
   * @param string $equipmentId equipment to retrieve
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
  ) {
  }
  // #endregion
}
