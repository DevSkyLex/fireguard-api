<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Equipment\ListEquipmentOpenWork;

use Intervention\Application\Contract\Resource\InterventionEquipmentWork;
use Shared\Application\Message\ResultMessage;

/**
 * Class ListEquipmentOpenWorkResult
 *
 * Carries authorized existing work without exposing persistence records.
 *
 * @category UseCase
 */
final readonly class ListEquipmentOpenWorkResult implements ResultMessage
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param list<InterventionEquipmentWork> $items authorized open work
   *
   * @return void
   */
  public function __construct(public array $items)
  {
  }
}
