<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\{Get, QueryParameter};
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Time\TimeEntryView;
use Intervention\Application\UseCase\Query\Time\GetTimeEntry\{GetTimeEntryQuery, GetTimeEntryResult};
use Intervention\Application\UseCase\Query\Time\ListTimeEntries\{ListTimeEntriesQuery, ListTimeEntriesResult};
use Intervention\Application\UseCase\Query\Time\ListTimeEntryVersions\{ListTimeEntryVersionsQuery, ListTimeEntryVersionsResult};
use Intervention\Presentation\Api\Dto\Output\{TimeEntryHistoryOutput, TimeEntryOutput, TimeJournalOutput};
use Intervention\Presentation\Api\Operation\InterventionTimeOperations;
use Intervention\Presentation\Api\Provider\InterventionTimeProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class InterventionTimeProviderTest
 *
 * Covers bounded journal and history HTTP translation.
 *
 * @category Test
 */
#[CoversClass(InterventionTimeProvider::class)]
final class InterventionTimeProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testJournalDispatchesBoundedPageAndExposesContinuation(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ListTimeEntriesQuery $query): bool => 2 === $query->page && 30 === $query->itemsPerPage && 'task' === $query->taskId))->willReturn(new ListTimeEntriesResult([], 61, 2, 30));
    $output = new InterventionTimeProvider($queries, $this->security())->provide(new Get(name: InterventionTimeOperations::LIST), ['taskId' => 'task'], ['filters' => ['page' => '2']]);
    self::assertInstanceOf(TimeJournalOutput::class, $output);
    self::assertSame(3, $output->nextPage);
    self::assertSame(61, $output->totalItems);
  }

  #[Test]
  public function testParsedApiParametersTakePrecedenceOverLegacyContext(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ListTimeEntriesQuery $query): bool => 3 === $query->page && 20 === $query->itemsPerPage))->willReturn(new ListTimeEntriesResult([], 61, 3, 20));
    $operation = new Get(name: InterventionTimeOperations::LIST, parameters: [
      'page' => new QueryParameter(extraProperties: ['_api_values' => '3']),
      'itemsPerPage' => new QueryParameter(extraProperties: ['_api_values' => '20']),
    ]);
    $output = new InterventionTimeProvider($queries, $this->security())->provide($operation, ['taskId' => 'task'], ['filters' => ['page' => '1', 'itemsPerPage' => '100']]);
    self::assertInstanceOf(TimeJournalOutput::class, $output);
    self::assertSame(3, $output->page);
    self::assertSame(20, $output->itemsPerPage);
    self::assertSame(4, $output->nextPage);
  }

  #[Test]
  public function testAbsentParsedApiParameterKeepsTheLegacyContextValue(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ListTimeEntriesQuery $query): bool => 2 === $query->page && 30 === $query->itemsPerPage))->willReturn(new ListTimeEntriesResult([], 61, 2, 30));
    $operation = new Get(name: InterventionTimeOperations::LIST, parameters: ['page' => new QueryParameter()]);
    $output = new InterventionTimeProvider($queries, $this->security())->provide($operation, ['taskId' => 'task'], ['filters' => ['page' => '2']]);
    self::assertInstanceOf(TimeJournalOutput::class, $output);
    self::assertSame(2, $output->page);
  }

  #[Test]
  public function testMalformedParsedApiPageSizeIsRejectedBeforeDispatch(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $operation = new Get(name: InterventionTimeOperations::LIST, parameters: ['itemsPerPage' => new QueryParameter(extraProperties: ['_api_values' => ['30']])]);
    $this->expectException(BadRequestHttpException::class);
    new InterventionTimeProvider($queries, $this->security())->provide($operation, ['taskId' => 'task']);
  }

  #[Test]
  public function testHistoryTranslatesAnExclusiveCursorWithoutExpandingTheJournal(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ListTimeEntryVersionsQuery $query): bool => 51 === $query->beforeRevision && 20 === $query->itemsPerPage && 'entry' === $query->entryId))->willReturn(new ListTimeEntryVersionsResult([], 60, 20, 31));
    $output = new InterventionTimeProvider($queries, $this->security())->provide(new Get(name: InterventionTimeOperations::VERSIONS), ['taskId' => 'task', 'entryId' => 'entry'], ['filters' => ['beforeRevision' => '51', 'itemsPerPage' => '20']]);
    self::assertInstanceOf(TimeEntryHistoryOutput::class, $output);
    self::assertSame(31, $output->nextBeforeRevision);
    self::assertSame(60, $output->totalItems);
  }

  #[Test]
  public function testRejectsOversizedHistoryBeforeAnyQuery(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(BadRequestHttpException::class);
    new InterventionTimeProvider($queries, $this->security())->provide(new Get(name: InterventionTimeOperations::VERSIONS), ['taskId' => 'task', 'entryId' => 'entry'], ['filters' => ['itemsPerPage' => '101']]);
  }

  #[Test]
  public function testCurrentEntryReadDispatchesOnlyTheSelectedIdentifier(): void
  {
    $entry = new TimeEntryView('entry', 'task', 'actor', '2026-01-01', 60, null, 50, false, 'actor', 'actor', 'now', 'now');
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (GetTimeEntryQuery $query): bool => 'task' === $query->taskId && 'entry' === $query->entryId))->willReturn(new GetTimeEntryResult($entry));
    $output = new InterventionTimeProvider($queries, $this->security())->provide(new Get(name: InterventionTimeOperations::GET), ['taskId' => 'task', 'entryId' => 'entry']);
    self::assertInstanceOf(TimeEntryOutput::class, $output);
    self::assertSame($entry, $output->entry);
  }

  /**
   * Method security
   *
   * Resolves one authenticated caller for HTTP translation.
   *
   * @access private
   *
   * @return Security security boundary stub
   */
  private function security(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user', 'user@example.test', 'hashed', ['ROLE_USER']));

    return $security;
  }
  // #endregion
}
