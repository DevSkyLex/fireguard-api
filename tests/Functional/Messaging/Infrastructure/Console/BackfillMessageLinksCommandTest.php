<?php

declare(strict_types=1);

namespace Tests\Functional\Messaging\Infrastructure\Console;

use Messaging\Application\UseCase\Command\Message\BackfillMessageLinks\BackfillMessageLinksResult;
use Messaging\Infrastructure\Console\BackfillMessageLinksCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use RuntimeException;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Console\Application as FrameworkApplication;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test BackfillMessageLinksCommandTest.
 *
 * @category Functional Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(BackfillMessageLinksCommand::class)]
final class BackfillMessageLinksCommandTest extends KernelTestCase
{
  // #region Properties
  /**
   * Property application.
   *
   * The kernel-backed console application, wired with the real command bus.
   */
  private FrameworkApplication $application;
  // #endregion

  // #region Setup
  /**
   * Method setUp.
   *
   * Boots the kernel and builds a console application from it.
   */
  protected function setUp(): void
  {
    $kernel = self::bootKernel();
    $this->application = new FrameworkApplication($kernel);
  }
  // #endregion

  // #region Structure
  /**
   * Tests that the command is registered under its configured name.
   */
  #[Test]
  public function commandIsRegisteredWithName(): void
  {
    self::assertTrue($this->application->has('app:messaging:backfill-links'));
  }
  // #endregion

  // #region Happy path
  /**
   * Tests that a completed backfill reports its aggregate summary.
   */
  #[Test]
  public function reportsSummaryOnSuccessfulBackfill(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())
      ->method('dispatch')
      ->willReturn($this->resultBatch(3, 2, null, false));

    $tester = $this->mockedTester($bus);
    $tester->execute([]);

    $tester->assertCommandIsSuccessful();
    self::assertStringContainsString('Processed 3 message(s) and extracted 2 unique link(s).', $tester->getDisplay());
  }

  /**
   * Tests that a dry run reports its summary as a note without persisting.
   */
  #[Test]
  public function reportsDryRunNoteWhenDryRunRequested(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())
      ->method('dispatch')
      ->willReturn($this->resultBatch(1, 4, null, false));

    $tester = $this->mockedTester($bus);
    $tester->execute(['--dry-run' => true]);

    $tester->assertCommandIsSuccessful();
    self::assertStringContainsString('Dry run:', $tester->getDisplay());
    self::assertStringContainsString('extracted 4 unique link(s).', $tester->getDisplay());
  }

  /**
   * Tests that batches are consumed until the cursor is exhausted, emitting
   * verbose per-batch progress along the way.
   */
  #[Test]
  public function processesMultipleBatchesUntilCursorExhausted(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnOnConsecutiveCalls(
        $this->resultBatch(3, 2, 'batch-cursor-1', true),
        $this->resultBatch(2, 2, 'batch-cursor-2', false),
      );

    $tester = $this->mockedTester($bus);
    $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

    $tester->assertCommandIsSuccessful();
    self::assertStringContainsString('Processed 3 message(s); cursor batch-cursor-1.', $tester->getDisplay());
    self::assertStringContainsString('Processed 5 message(s) and extracted 4 unique link(s).', $tester->getDisplay());
  }
  // #endregion

  // #region Failure branches
  /**
   * Tests that an out-of-range batch size is rejected before dispatch.
   */
  #[Test]
  public function rejectsInvalidBatchSize(): void
  {
    $tester = new CommandTester($this->realCommand());
    $tester->execute(['--batch-size' => '0']);

    self::assertSame(Command::INVALID, $tester->getStatusCode());
    self::assertStringContainsString('must be an integer between 1 and 500', $tester->getDisplay());
  }

  /**
   * Tests that a result of the wrong type is reported as a failure.
   */
  #[Test]
  public function failsWhenBusReturnsUnexpectedResult(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())
      ->method('dispatch')
      ->willReturn(new class () implements ResultMessage {});

    $tester = $this->mockedTester($bus);
    $tester->execute([]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('unexpected result', $tester->getDisplay());
  }

  /**
   * Tests that a thrown error is caught and reported as a command failure.
   */
  #[Test]
  public function failsWhenBackfillThrows(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('storage down'));

    $tester = $this->mockedTester($bus);
    $tester->execute([]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Messaging link backfill failed', $tester->getDisplay());
    self::assertStringContainsString('storage down', $tester->getDisplay());
  }
  // #endregion

  // #region Helpers
  /**
   * Method realCommand.
   *
   * Fetches the container-wired command from the kernel application.
   */
  private function realCommand(): Command
  {
    return $this->application->find('app:messaging:backfill-links');
  }

  /**
   * Method mockedTester.
   *
   * Builds a command tester around a fresh command bound to a mocked bus.
   *
   * @param CommandBusPort $bus the mocked inbound command bus
   */
  private function mockedTester(CommandBusPort $bus): CommandTester
  {
    $command = new BackfillMessageLinksCommand($bus);
    new ConsoleApplication()->addCommand($command);

    return new CommandTester($command);
  }

  /**
   * Method resultBatch.
   *
   * Builds a backfill result describing one processed batch.
   *
   * @param int $processed the number of messages inspected in the batch
   * @param int $extracted the number of unique links found in the batch
   * @param ?string $cursor the cursor for the next batch, or null when done
   * @param bool $hasMore whether another batch may exist
   */
  private function resultBatch(int $processed, int $extracted, ?string $cursor, bool $hasMore): BackfillMessageLinksResult
  {
    return new BackfillMessageLinksResult(
      processedMessages: $processed,
      extractedLinks: $extracted,
      nextCursor: $cursor,
      hasMore: $hasMore,
    );
  }
  // #endregion
}
