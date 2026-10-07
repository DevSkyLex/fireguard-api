<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Provider;

use ApiPlatform\Metadata\{Get, GetCollection};
use ApiPlatform\State\Pagination\TraversablePaginator;
use DateTimeImmutable;
use Maintenance\Application\Contract\Plan\{MaintenancePlanDetails, MaintenancePlanState};
use Maintenance\Application\UseCase\Query\Plan\ReadMaintenancePlans\{ReadMaintenancePlansQuery, ReadMaintenancePlansResult};
use Maintenance\Domain\Exception\MaintenanceAccessDeniedException;
use Maintenance\Presentation\Api\Dto\Output\{MaintenancePlanEngineOutput, MaintenancePlanOutput, MaintenancePlanPreviewOutput};
use Maintenance\Presentation\Api\Factory\MaintenancePlanOutputFactory;
use Maintenance\Presentation\Api\Operation\MaintenancePlanOperations;
use Maintenance\Presentation\Api\Provider\MaintenancePlanProvider;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function iterator_to_array;

/**
 * Class MaintenancePlanProviderTest
 *
 * Verifies metadata-selected reads, scalar output and bounded filter forwarding.
 *
 * @category Tests
 */
#[CoversClass(MaintenancePlanProvider::class)]
final class MaintenancePlanProviderTest extends TestCase
{
  // #region Constants
  /**
   * Constant ORGANIZATION
   */
  private const string ORGANIZATION = '018fa001-1111-7111-8111-111111111111';

  /**
   * Constant ACTOR
   */
  private const string ACTOR = '018fa002-1111-7111-8111-111111111111';

  /**
   * Constant PLAN
   */
  private const string PLAN = '018fa003-1111-7111-8111-111111111111';

  /**
   * Constant EQUIPMENT
   */
  private const string EQUIPMENT = '018fa004-1111-7111-8111-111111111111';
  // #endregion

  // #region Methods
  /**
   * Method testItemMetadataSelectsDetailAndMapsPlanDates
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testItemMetadataSelectsDetailAndMapsPlanDates(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $details = $this->details();
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadMaintenancePlansQuery $query): bool => self::ORGANIZATION === $query->organizationId && self::ACTOR === $query->actorUserId && 'detail' === $query->action && self::PLAN === $query->planId))->willReturn(new ReadMaintenancePlansResult(items: [$details], total: 1, mode: 'plans'));
    $provider = new MaintenancePlanProvider($bus, $this->actor(), new MaintenancePlanOutputFactory(), new RequestStack());

    $output = $provider->provide(new Get(name: MaintenancePlanOperations::GET), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);

    self::assertInstanceOf(MaintenancePlanOutput::class, $output);
    self::assertSame(self::PLAN, $output->id);
    self::assertSame(self::EQUIPMENT, $output->equipmentId);
    self::assertSame('control', $output->operationKind);
    self::assertSame('2026-01-31T00:00:00+00:00', $output->anchorAt);
    self::assertSame('2026-02-28T00:00:00+00:00', $output->nextDueAt);
    self::assertFalse($output->active);
  }

  /**
   * Method testCollectionForwardsFilterAndPaginatorMetadata
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCollectionForwardsFilterAndPaginatorMetadata(): void
  {
    $requests = new RequestStack();
    $requests->push(Request::create('/api/maintenance/plans', 'GET', ['page' => '3', 'itemsPerPage' => '20', 'equipmentId' => self::EQUIPMENT, 'operationKind' => 'control', 'search' => 'Annual']));
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadMaintenancePlansQuery $query): bool => self::ORGANIZATION === $query->organizationId && self::ACTOR === $query->actorUserId && 'list' === $query->action && null === $query->planId && 3 === $query->page && 20 === $query->itemsPerPage && self::EQUIPMENT === $query->equipmentId && 'control' === $query->operationKind && 'Annual' === $query->search))->willReturn(new ReadMaintenancePlansResult(items: [$this->details()], total: 61, mode: 'plans'));
    $provider = new MaintenancePlanProvider($bus, $this->actor(), new MaintenancePlanOutputFactory(), $requests);

    $output = $provider->provide(new GetCollection(name: MaintenancePlanOperations::LIST), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(3.0, $output->getCurrentPage());
    self::assertSame(20.0, $output->getItemsPerPage());
    self::assertSame(61.0, $output->getTotalItems());
    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertInstanceOf(MaintenancePlanOutput::class, $items[0]);
    self::assertSame(self::PLAN, $items[0]->id);
  }

  /**
   * Method testCollectionWithoutRequestUsesDefaultPage
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCollectionWithoutRequestUsesDefaultPage(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadMaintenancePlansQuery $query): bool => 'list' === $query->action && 1 === $query->page && 30 === $query->itemsPerPage && null === $query->equipmentId && null === $query->operationKind && null === $query->search))->willReturn(new ReadMaintenancePlansResult());
    $provider = new MaintenancePlanProvider($bus, $this->actor(), new MaintenancePlanOutputFactory(), new RequestStack());

    $output = $provider->provide(new GetCollection(name: MaintenancePlanOperations::LIST), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(0.0, $output->getTotalItems());
    self::assertCount(0, iterator_to_array($output));
  }

  /**
   * Method testPagingIsClampedBeforeDispatch
   *
   * @access public
   *
   * @param int $page the requested page
   * @param int $size the requested size
   * @param int $expectedPage the bounded page
   * @param int $expectedSize the bounded size
   *
   * @return void
   */
  #[Test]
  #[DataProvider('pagingCases')]
  public function testPagingIsClampedBeforeDispatch(int $page, int $size, int $expectedPage, int $expectedSize): void
  {
    $requests = new RequestStack();
    $requests->push(Request::create('/api/maintenance/plans', 'GET', ['page' => (string) $page, 'itemsPerPage' => (string) $size]));
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadMaintenancePlansQuery $query): bool => $expectedPage === $query->page && $expectedSize === $query->itemsPerPage))->willReturn(new ReadMaintenancePlansResult());
    $provider = new MaintenancePlanProvider($bus, $this->actor(), new MaintenancePlanOutputFactory(), $requests);

    $output = $provider->provide(new GetCollection(name: MaintenancePlanOperations::LIST), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame((float) $expectedPage, $output->getCurrentPage());
    self::assertSame((float) $expectedSize, $output->getItemsPerPage());
  }

  /**
   * Method pagingCases
   *
   * @access public
   *
   * @return iterable<string, array{int, int, int, int}> bounded paging examples
   */
  public static function pagingCases(): iterable
  {
    yield 'minimum page and maximum size' => [-4, 500, 1, 100];
    yield 'minimum size' => [2, 0, 2, 1];
  }

  /**
   * Method testPreviewMetadataProducesIsoDates
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPreviewMetadataProducesIsoDates(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadMaintenancePlansQuery $query): bool => 'preview' === $query->action && self::PLAN === $query->planId && self::ORGANIZATION === $query->organizationId))->willReturn(new ReadMaintenancePlansResult(dates: [new DateTimeImmutable('2026-02-28T08:00:00+00:00'), new DateTimeImmutable('2026-03-31T08:00:00+00:00'), new DateTimeImmutable('2026-04-30T08:00:00+00:00')]));
    $provider = new MaintenancePlanProvider($bus, $this->actor(), new MaintenancePlanOutputFactory(), new RequestStack());

    $output = $provider->provide(new Get(name: MaintenancePlanOperations::PREVIEW), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);

    self::assertInstanceOf(MaintenancePlanPreviewOutput::class, $output);
    self::assertSame(['2026-02-28T08:00:00+00:00', '2026-03-31T08:00:00+00:00', '2026-04-30T08:00:00+00:00'], $output->dates);
  }

  /**
   * Method testEngineMetadataProducesAuthorityAndPreparedCount
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEngineMetadataProducesAuthorityAndPreparedCount(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadMaintenancePlansQuery $query): bool => 'engine' === $query->action && null === $query->planId && self::ORGANIZATION === $query->organizationId))->willReturn(new ReadMaintenancePlansResult(mode: 'legacy', preparedCount: 7));
    $provider = new MaintenancePlanProvider($bus, $this->actor(), new MaintenancePlanOutputFactory(), new RequestStack());

    $output = $provider->provide(new Get(name: MaintenancePlanOperations::ENGINE), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(MaintenancePlanEngineOutput::class, $output);
    self::assertSame('legacy', $output->mode);
    self::assertSame(7, $output->preparedCount);
  }

  /**
   * Method testUnauthenticatedRequestDoesNotDispatchQuery
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnauthenticatedRequestDoesNotDispatchQuery(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $provider = new MaintenancePlanProvider($bus, $this->actor(null), new MaintenancePlanOutputFactory(), new RequestStack());
    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(name: MaintenancePlanOperations::LIST), ['organizationId' => self::ORGANIZATION]);
  }

  /**
   * Method testInvalidRouteIdentifiersDoNotDispatchQuery
   *
   * @access public
   *
   * @param array<string, mixed> $variables the invalid route variables
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidRoutes')]
  public function testInvalidRouteIdentifiersDoNotDispatchQuery(array $variables): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $provider = new MaintenancePlanProvider($bus, $this->actor(), new MaintenancePlanOutputFactory(), new RequestStack());
    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(name: MaintenancePlanOperations::GET), $variables);
  }

  /**
   * Method invalidRoutes
   *
   * @access public
   *
   * @return iterable<string, array{array<string, mixed>}> invalid route identifiers
   */
  public static function invalidRoutes(): iterable
  {
    yield 'missing organization' => [[]];
    yield 'non-string organization' => [['organizationId' => 123]];
    yield 'non-string plan' => [['organizationId' => self::ORGANIZATION, 'id' => ['bad']]];
  }

  /**
   * Method testBusinessDenialPropagatesToCentralErrorMapper
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testBusinessDenialPropagatesToCentralErrorMapper(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->willThrowException(new MaintenanceAccessDeniedException('Missing permission.'));
    $provider = new MaintenancePlanProvider($bus, $this->actor(), new MaintenancePlanOutputFactory(), new RequestStack());
    $this->expectException(MaintenanceAccessDeniedException::class);

    $provider->provide(new Get(name: MaintenancePlanOperations::GET), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);
  }

  /**
   * Method actor
   *
   * @access private
   *
   * @param ?string $id the authenticated actor or null
   *
   * @return CurrentActorPort the mocked actor port
   */
  private function actor(?string $id = self::ACTOR): CurrentActorPort
  {
    $actor = $this->createMock(CurrentActorPort::class);
    $actor->expects(self::once())->method('userId')->willReturn($id);

    return $actor;
  }

  /**
   * Method details
   *
   * @access private
   *
   * @return MaintenancePlanDetails a prepared control plan
   */
  private function details(): MaintenancePlanDetails
  {
    return new MaintenancePlanDetails(new MaintenancePlanState(
      self::PLAN,
      self::ORGANIZATION,
      self::EQUIPMENT,
      null,
      'fire_extinguisher',
      'Annual control',
      'control',
      'P1M',
      'fixed',
      new DateTimeImmutable('2026-01-31T00:00:00+00:00'),
      new DateTimeImmutable('2026-02-28T00:00:00+00:00'),
      false,
      null,
      null,
      null,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ), null);
  }
  // #endregion
}
