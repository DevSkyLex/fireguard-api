<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Inventory;

/** Class InventoryInterventionContext. Publication state needed for late physical declarations. @category Contract */
final readonly class InventoryInterventionContext
{
  public function __construct(public bool $published)
  {
  }
}
