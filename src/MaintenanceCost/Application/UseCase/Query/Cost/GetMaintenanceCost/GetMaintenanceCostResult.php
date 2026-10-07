<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Cost\GetMaintenanceCost;

use MaintenanceCost\Application\Contract\Cost\MaintenanceCostView;
use Shared\Application\Message\ResultMessage;

/** Class GetMaintenanceCostResult. Private finance output. @category Result */
final readonly class GetMaintenanceCostResult implements ResultMessage
{
  public function __construct(public MaintenanceCostView $view)
  {
  }
}
