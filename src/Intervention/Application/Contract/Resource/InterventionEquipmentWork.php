<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

/**
 * Class InterventionEquipmentWork
 *
 * Projects an open equipment task so the dossier can navigate to existing work.
 *
 * @category Contract
 */
final readonly class InterventionEquipmentWork
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $interventionId owning intervention
   * @param int $number stable intervention number
   * @param string $name intervention name
   * @param string $status intervention lifecycle status
   * @param string $workItemId equipment task identity
   * @param string $action prepared action
   * @param string $workItemStatus task lifecycle status
   *
   * @return void
   */
  public function __construct(public string $interventionId, public int $number, public string $name, public string $status, public string $workItemId, public string $action, public string $workItemStatus)
  {
  }
}
