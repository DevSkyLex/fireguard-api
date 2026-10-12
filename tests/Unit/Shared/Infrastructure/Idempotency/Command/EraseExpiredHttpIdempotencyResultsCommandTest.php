<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency\Command;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyRetentionPort;
use Shared\Infrastructure\Idempotency\Command\EraseExpiredHttpIdempotencyResultsCommand;
use Symfony\Component\Console\Tester\CommandTester;

/** Verifies independent database payload erasure and non-sensitive maintenance output. */
final class EraseExpiredHttpIdempotencyResultsCommandTest extends TestCase
{
  public function testUsesOneAbsoluteDeadlineForBothOwningDatabasesAndKeepsTombstones(): void
  {
    $now = new DateTimeImmutable('2026-10-11T12:00:00Z');
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);
    $main = $this->createMock(IdempotencyRetentionPort::class);
    $auth = $this->createMock(IdempotencyRetentionPort::class);
    $owner = $this->createMock(IdempotencyRetentionPort::class);
    $main->expects(self::once())->method('eraseExpiredResults')->with($now)->willReturn(2);
    $auth->expects(self::once())->method('eraseExpiredResults')->with($now)->willReturn(1);
    $owner->expects(self::once())->method('eraseExpiredResults')->with($now)->willReturn(3);
    $tester = new CommandTester(new EraseExpiredHttpIdempotencyResultsCommand($main, $auth, $clock, [$owner]));
    self::assertSame(0, $tester->execute([]));
    self::assertStringContainsString('main=2, auth=1, contributed=3. Durable tombstones retained.', $tester->getDisplay());
  }
}
