<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost;

use MaintenanceCost\Application\Contract\Cost\MaintenanceCostView;
use Shared\Application\Message\ResultMessage;

/** Class WriteMaintenanceCostResult. Current finance after an authorized mutation. @category Result */
final readonly class WriteMaintenanceCostResult implements ResultMessage
{
  public function __construct(public MaintenanceCostView $view)
  {
  }
}
