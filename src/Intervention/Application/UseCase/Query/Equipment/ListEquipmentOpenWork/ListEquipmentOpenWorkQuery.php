<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Equipment\ListEquipmentOpenWork;

use Shared\Application\Message\QueryMessage;

/**
 * Class ListEquipmentOpenWorkQuery
 *
 * Requests existing equipment work in an explicitly selected organization.
 *
 * @category UseCase
 */
final readonly class ListEquipmentOpenWorkQuery implements QueryMessage
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $userId authenticated caller
   * @param string $organizationId requested organization
   * @param string $equipmentId requested equipment
   *
   * @return void
   */
  public function __construct(public string $userId, public string $organizationId, public string $equipmentId)
  {
  }
}
