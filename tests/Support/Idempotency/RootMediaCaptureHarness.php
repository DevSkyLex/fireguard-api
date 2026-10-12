<?php

declare(strict_types=1);

namespace Tests\Support\Idempotency;

use LogicException;
use Shared\Application\Contract\Idempotency\IdempotencyLease;
use Shared\Application\Message\{ResultMessage, VoidResult};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Application\Port\Outbound\RootTransactionManagerPort;
use Throwable;

/**
 * Class RootMediaCaptureHarness
 *
 * Records owner-level capture order and rejects captures after the final commit.
 * PostgreSQL integration tests establish the actual rollback and storage guarantees.
 *
 * @category Test Support
 */
final class RootMediaCaptureHarness implements RootTransactionManagerPort, HttpIdempotencyExecutionPort
{
  /**
   * @var list<string> observable mutation/capture/commit order
   */
  public array $events = [];

  public ?ResultMessage $committedSnapshot = null;

  public ?Throwable $captureFailure = null;

  public ?Throwable $claimFailure = null;

  private bool $active = false;

  private ?ResultMessage $pending = null;

  public function assertCanStart(): void
  {
    if ($this->active) {
      throw new LogicException('The final media transaction is already active.');
    }
  }

  public function transactional(callable $operation): mixed
  {
    $this->assertCanStart();
    $this->active = true;
    $this->events[] = 'begin';

    try {
      $this->events[] = 'claim';
      if (null !== $this->claimFailure) {
        throw $this->claimFailure;
      }
      $result = $operation();
      $this->events[] = 'commit';
      $this->committedSnapshot = $this->pending;

      return $result;
    } catch (Throwable $failure) {
      $this->events[] = 'rollback';
      $this->pending = null;

      throw $failure;
    } finally {
      $this->active = false;
    }
  }

  public function captureResult(ResultMessage $result): void
  {
    if (!$this->active) {
      throw new LogicException('Capture must precede the final media commit.');
    }
    $this->events[] = 'capture';
    if (null !== $this->captureFailure) {
      throw $this->captureFailure;
    }
    $this->pending = $result;
  }

  public function captureVoid(): void
  {
    $this->captureResult(new VoidResult());
  }

  public function executionLease(): ?IdempotencyLease
  {
    return null;
  }

  public function providerKey(string $purpose): ?string
  {
    return null;
  }

  public function externalEffectStarted(string $purpose): void
  {
  }

  public function isReplay(): bool
  {
    return false;
  }
}
