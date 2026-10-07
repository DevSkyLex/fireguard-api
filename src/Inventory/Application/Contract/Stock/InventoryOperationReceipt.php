<?php

declare(strict_types=1);

namespace Inventory\Application\Contract\Stock;

/** @category Contract */
final readonly class InventoryOperationReceipt
{
  /**
   * @param array<string,mixed> $response
   */
  public function __construct(public string $organizationId, public string $clientOperationId, public string $payloadHash, public array $response)
  {
  }
}
