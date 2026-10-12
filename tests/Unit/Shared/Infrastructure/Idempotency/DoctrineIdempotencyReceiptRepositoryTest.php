<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use LogicException;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\IdempotencyLease;
use Shared\Infrastructure\Persistence\Doctrine\Repository\DoctrineIdempotencyReceiptRepository;

use function str_repeat;

/** Verifies that receipt operations cannot silently use the wrong transaction boundary. */
final class DoctrineIdempotencyReceiptRepositoryTest extends TestCase
{
  public function testRejectsSharedReservationAndWriteConnection(): void
  {
    $connection = $this->createStub(Connection::class);
    $this->expectException(LogicException::class);
    new DoctrineIdempotencyReceiptRepository($connection, $connection);
  }

  public function testRefusesReservationInsideTheWriteTransaction(): void
  {
    $write = $this->createStub(Connection::class);
    $write->method('isTransactionActive')->willReturn(true);
    $reservation = $this->createMock(Connection::class);
    $reservation->expects(self::never())->method('executeStatement');
    $this->expectException(LogicException::class);
    new DoctrineIdempotencyReceiptRepository($write, $reservation)->reserve($this->lease(), new DateTimeImmutable());
  }

  public function testRefusesClaimWithoutTheOwningTransaction(): void
  {
    $write = $this->createStub(Connection::class);
    $write->method('isTransactionActive')->willReturn(false);
    $reservation = $this->createMock(Connection::class);
    $reservation->expects(self::never())->method('executeStatement');
    $this->expectException(LogicException::class);
    new DoctrineIdempotencyReceiptRepository($write, $reservation)->claim($this->lease(), new DateTimeImmutable());
  }

  public function testRefusesResultBeforeClaimingTheOwnershipFence(): void
  {
    $write = $this->createMock(Connection::class);
    $write->method('isTransactionActive')->willReturn(true);
    $write->expects(self::never())->method('executeStatement');
    $this->expectException(LogicException::class);
    $now = new DateTimeImmutable();
    new DoctrineIdempotencyReceiptRepository($write)->complete($this->lease(), 'result', false, $now, $now->modify('+24 hours'));
  }

  public function testRefusesRollbackReleaseWhileWriteTransactionStillActive(): void
  {
    $write = $this->createStub(Connection::class);
    $write->method('isTransactionActive')->willReturn(true);
    $reservation = $this->createMock(Connection::class);
    $reservation->expects(self::never())->method('executeStatement');
    $this->expectException(LogicException::class);
    new DoctrineIdempotencyReceiptRepository($write, $reservation)->releaseAfterRollback($this->lease());
  }

  public function testConfirmedRollbackCannotDeleteAnotherOwnerOrUnknownOutcome(): void
  {
    $write = $this->createStub(Connection::class);
    $write->method('isTransactionActive')->willReturn(false);
    $reservation = $this->createMock(Connection::class);
    $reservation->method('isTransactionActive')->willReturn(false);
    $lease = $this->lease();
    $reservation->expects(self::once())->method('executeStatement')->with(
      "UPDATE http_idempotency_receipts SET state = 'pending', owner_token = '', lease_expires_at = TIMESTAMPTZ 'epoch' WHERE scope_hash = ? AND key_hash = ? AND owner_token = ? AND state IN ('pending', 'writing')",
      [$lease->scopeHash, $lease->keyHash, $lease->ownerToken],
    )->willReturn(0);
    new DoctrineIdempotencyReceiptRepository($write, $reservation)->releaseAfterRollback($lease);
  }

  private function lease(): IdempotencyLease
  {
    return new IdempotencyLease(str_repeat('s', 64), str_repeat('k', 64), str_repeat('f', 64), str_repeat('o', 64), new DateTimeImmutable('+60 seconds'));
  }
}
