<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Event;

/** Invalidation fact: consumers must reload current published equipment state. */
final readonly class EquipmentChangedEvent
{
  public function __construct(public string $organizationId, public string $equipmentId)
  {
  }
}
