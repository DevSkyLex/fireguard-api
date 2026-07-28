<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Application\UseCase\Query\ListImportJobs\{ListImportJobsQuery, ListImportJobsResult};
use Import\Domain\Exception\ImportJobNotFoundException;
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Import\Presentation\Api\Provider\ImportJobCollectionProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function iterator_to_array;

/**
 * Test ImportJobCollectionProviderTest.
 *
 * Import jobs are organization-scoped, so the required `organization`
 * filter is a tenant-isolation boundary, not a convenience: without it the
 * endpoint must refuse rather than fall back to an unscoped listing. The
 * page-size clamp keeps a hostile `itemsPerPage` from turning the endpoint
 * into a full-table dump.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobCollectionProvider::class)]
final class ImportJobCollectionProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655502001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655502002';

  private const string IMPORT_JOB_ID = '550e8400-e29b-41d4-a716-446655502003';

  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'unknown job' => [
      ImportJobNotFoundException::withId(self::IMPORT_JOB_ID),
      NotFoundHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Unknown import kind.'),
      BadRequestHttpException::class,
    ];
  }

  /**
   * @return iterable<string, array{array<string, string|int>, int, int}>
   */
  public static function paginationProvider(): iterable
  {
    yield 'defaults' => [[], 1, 30];
    yield 'explicit window' => [['page' => 3, 'itemsPerPage' => 50], 3, 50];
    yield 'page below one is clamped' => [['page' => 0], 1, 30];
    yield 'items per page is capped at one hundred' => [['itemsPerPage' => 5000], 1, 100];
    yield 'items per page below one is clamped' => [['itemsPerPage' => 0], 1, 1];
  }

  #[Test]
  public function testItReturnsThePageOfImportJobs(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListImportJobsQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId
        && self::USER_ID === $query->userId
        && null === $query->kind))
      ->willReturn(new ListImportJobsResult(items: [$this->view()], page: 1, itemsPerPage: 30, total: 1));

    $output = $this->createProvider($queryBus, ['organization' => self::ORGANIZATION_ID])
      ->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(1.0, $output->getTotalItems());

    $items = iterator_to_array($output);
    self::assertCount(1, $items);

    $item = $items[0];
    self::assertInstanceOf(ImportJobOutput::class, $item);
    self::assertSame(self::IMPORT_JOB_ID, $item->id);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $item->organization);
    self::assertSame('equipment', $item->kind);
  }

  #[Test]
  public function testItAcceptsAnOrganizationIri(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListImportJobsQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId))
      ->willReturn(new ListImportJobsResult(items: [], page: 1, itemsPerPage: 30, total: 0));

    $this->createProvider($queryBus, ['organization' => '/api/organizations/' . self::ORGANIZATION_ID])
      ->provide(new GetCollection());
  }

  #[Test]
  public function testItForwardsANonBlankKindFilter(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListImportJobsQuery $query): bool => 'facility' === $query->kind))
      ->willReturn(new ListImportJobsResult(items: [], page: 1, itemsPerPage: 30, total: 0));

    $this->createProvider($queryBus, ['organization' => self::ORGANIZATION_ID, 'kind' => 'facility'])
      ->provide(new GetCollection());
  }

  #[Test]
  public function testItDropsABlankKindFilter(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListImportJobsQuery $query): bool => null === $query->kind))
      ->willReturn(new ListImportJobsResult(items: [], page: 1, itemsPerPage: 30, total: 0));

    $this->createProvider($queryBus, ['organization' => self::ORGANIZATION_ID, 'kind' => ''])
      ->provide(new GetCollection());
  }

  /**
   * @param array<string, string|int> $query
   */
  #[Test]
  #[DataProvider('paginationProvider')]
  public function testItClampsThePaginationWindow(array $query, int $expectedPage, int $expectedItemsPerPage): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListImportJobsQuery $listQuery): bool => $expectedPage === $listQuery->page
        && $expectedItemsPerPage === $listQuery->itemsPerPage))
      ->willReturn(new ListImportJobsResult(items: [], page: $expectedPage, itemsPerPage: $expectedItemsPerPage, total: 0));

    $this->createProvider($queryBus, ['organization' => self::ORGANIZATION_ID] + $query)
      ->provide(new GetCollection());
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ImportJobCollectionProvider(
      queryBus: $queryBus,
      outputFactory: new ImportJobOutputFactory(),
      security: $security,
      requestStack: $this->requestStack([]),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testItRefusesAListingWithoutAnOrganizationFilter(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The organization filter is required.');

    $this->createProvider($queryBus, [])->provide(new GetCollection());
  }

  #[Test]
  public function testItRefusesABlankOrganizationFilter(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($queryBus, ['organization' => ''])->provide(new GetCollection());
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testItMapsEachDomainFailureToItsHttpStatus(Throwable $failure, string $expected): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $this->expectException($expected);

    $this->createProvider($queryBus, ['organization' => self::ORGANIZATION_ID])->provide(new GetCollection());
  }

  #[Test]
  public function testItRethrowsAnUnrecognisedFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('database is down'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('database is down');

    $this->createProvider($queryBus, ['organization' => self::ORGANIZATION_ID])->provide(new GetCollection());
  }

  /**
   * @param array<string, string|int> $query
   */
  private function createProvider(QueryBusPort $queryBus, array $query): ImportJobCollectionProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return new ImportJobCollectionProvider(
      queryBus: $queryBus,
      outputFactory: new ImportJobOutputFactory(),
      security: $security,
      requestStack: $this->requestStack($query),
    );
  }

  /**
   * @param array<string, string|int> $query
   */
  private function requestStack(array $query): RequestStack
  {
    $request = new Request();
    foreach ($query as $key => $value) {
      $request->query->set($key, $value);
    }

    $requestStack = new RequestStack();
    $requestStack->push($request);

    return $requestStack;
  }

  private function view(): GetImportJobResult
  {
    $now = new DateTimeImmutable('2026-04-01T09:00:00+00:00');

    return new GetImportJobResult(
      importJobId: self::IMPORT_JOB_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'equipment',
      status: 'completed',
      originalFilename: 'equipements.csv',
      totalRows: 12,
      processedRows: 12,
      successfulRows: 11,
      failedRows: 1,
      errorReport: [
        ['rowNumber' => 4, 'column' => 'type', 'code' => 'invalid_choice', 'message' => 'Unknown equipment type.'],
      ],
      jobError: null,
      createdBy: self::USER_ID,
      createdAt: $now,
      startedAt: $now,
      completedAt: $now,
      updatedAt: $now,
    );
  }
}
