<?php

declare(strict_types=1);

namespace Procurement\Application\UseCase\Command\ManageProcurement;

use Shared\Application\Message\ResultMessage;

/** Transport-neutral authorized projection returned only after persistence succeeds. */
final readonly class ManageProcurementResult implements ResultMessage
{
  /**
   * @param array<string,mixed> $data
   */
  public function __construct(public string $kind, public array $data, public bool $replayed = false)
  {
  }
}
