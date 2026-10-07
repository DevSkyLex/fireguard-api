<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Procurement;

/** @category Contract */
final readonly class EquipmentReserveReceiptResult
{
  /**
   * @param list<string> $equipmentIds
   */
  public function __construct(public array $equipmentIds, public ?string $blockedReason = null)
  {
  }
}
