<?php

declare(strict_types=1);

namespace Import\Application\Port\Outbound;

use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\ImportJobId;

/** Owns exclusive processing leases and atomic row receipts in main. */
interface ImportExecutionPort
{
  /**
   * Returns null for an absent/terminal job; a live competing lease is retryable.
   */
  public function claim(ImportJobId $id, string $owner): ?ImportJob;

  /**
   * Rereads the job under its lease lock, then commits the operation, progress
   * and optional row receipt together. Already confirmed rows are skipped.
   *
   * @param callable(ImportJob):?string $operation returns the created resource ID, when applicable
   */
  public function run(ImportJobId $id, string $owner, callable $operation, ?int $rowNumber = null): ImportJob;

  public function release(ImportJobId $id, string $owner): void;

  public function canResume(ImportJobId $id): bool;

  /**
   * @param callable(ImportJob):void $enqueue recorded in the same transaction
   */
  public function resume(ImportJobId $id, callable $enqueue): ImportJob;
}
