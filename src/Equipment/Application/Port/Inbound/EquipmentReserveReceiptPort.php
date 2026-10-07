<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Inbound;

use Equipment\Application\Contract\Procurement\{EquipmentReserveReceiptRequest,EquipmentReserveReceiptResult};

/** Batch main-transaction reserve creation; Procurement owns receipt-unit idempotency. @category Port */
interface EquipmentReserveReceiptPort
{
  public function supportsType(string $organizationId, string $typeCode): bool;

  public function reserve(EquipmentReserveReceiptRequest $request): EquipmentReserveReceiptResult;
}
