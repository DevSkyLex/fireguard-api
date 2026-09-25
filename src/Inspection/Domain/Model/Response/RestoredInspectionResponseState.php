<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Response;

use Inspection\Domain\ValueObject\InspectionResponseStatus;

/** Persisted answer and offline-sync lifecycle state. */
final readonly class RestoredInspectionResponseState
{
  public function __construct(
    public ?string $interventionId,
    public ?string $clientId,
    public InspectionResponseStatus $status,
    public int $revision,
    public string $itemKey,
    public mixed $value,
  ) {
  }
}
