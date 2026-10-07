<?php

declare(strict_types=1);

namespace Procurement\Application\Contract;

/** Stable offline operation keys cannot be reused for a different physical declaration. */
final readonly class ProcurementOperationState
{
  /**
   * @param array<string,mixed> $declaration
   */
  public function __construct(public string $organizationId, public string $clientOperationId, public string $kind, public string $fingerprint, public string $receiptId, public array $declaration = [])
  {
  }
}
