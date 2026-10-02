<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Console\InspectWorkerQueuesCommand;
use Symfony\Component\Console\Tester\CommandTester;

use function str_contains;

final class InspectWorkerQueuesCommandTest extends TestCase
{
  public function testBothExplicitDatabaseHistoriesAreObservedWithoutPayloadReads(): void
  {
    $auth = $this->createMock(Connection::class);
    $main = $this->createMock(Connection::class);
    foreach ([$auth, $main] as $connection) {
      $connection->expects(self::once())->method('fetchAllAssociative')->with(self::callback(static fn (string $query): bool => !str_contains($query, 'body') && str_contains($query, 'available_at')))->willReturn([]);
    }
    $tester = new CommandTester(new InspectWorkerQueuesCommand($auth, $main));
    self::assertSame(0, $tester->execute([]));
    self::assertStringContainsString('"database":"auth"', $tester->getDisplay());
    self::assertStringContainsString('"database":"main"', $tester->getDisplay());
  }

  public function testOldDueWorkAndFailedMessagesProduceAnUnhealthyCheck(): void
  {
    foreach ([['queue_name' => 'async', 'messages' => '1', 'overdue_seconds' => '901'], ['queue_name' => 'failed', 'messages' => '1', 'overdue_seconds' => '0']] as $queue) {
      $auth = $this->createStub(Connection::class);
      $main = $this->createStub(Connection::class);
      $auth->method('fetchAllAssociative')->willReturn([$queue]);
      $main->method('fetchAllAssociative')->willReturn([]);
      self::assertSame(1, new CommandTester(new InspectWorkerQueuesCommand($auth, $main))->execute([]));
    }
  }

  public function testInvalidThresholdIsRejectedBeforeDatabaseAccess(): void
  {
    $auth = $this->createMock(Connection::class);
    $main = $this->createMock(Connection::class);
    $auth->expects(self::never())->method('fetchAllAssociative');
    $main->expects(self::never())->method('fetchAllAssociative');
    self::assertSame(2, new CommandTester(new InspectWorkerQueuesCommand($auth, $main))->execute(['--max-age' => '0']));
  }
}
