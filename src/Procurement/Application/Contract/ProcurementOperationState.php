<?php

declare(strict_types=1);

namespace Procurement\Application\Contract;

/** Stable operation keys retain creation and physical declarations without repeating their effects. */
final readonly class ProcurementOperationState
{
  /**
   * @param array<string,mixed> $declaration
   * @param string $receiptId the created resource UUID for creation kinds, otherwise the physical receipt UUID
   */
  public function __construct(public string $organizationId, public string $clientOperationId, public string $kind, public string $fingerprint, public string $receiptId, public array $declaration = [])
  {
  }
}
