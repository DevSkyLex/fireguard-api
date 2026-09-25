<?php

declare(strict_types=1);

namespace Compliance\Domain\Model\Snapshot;

/** Persisted PDF identity and integrity metadata for a safety register snapshot. */
final readonly class SafetyRegisterStoredFile
{
  public function __construct(
    public string $contentHash,
    public int $sizeBytes,
    public string $storagePath,
  ) {
  }
}
