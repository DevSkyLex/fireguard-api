<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Approval\Application\UseCase\Query\Request\GetApprovalRequest\GetApprovalRequestResult;
use Approval\Application\UseCase\Query\Request\ListApprovalRequests\{ListApprovalRequestsQuery, ListApprovalRequestsResult};
use Approval\Domain\Exception\ApprovalRequestNotFoundException;
use Approval\Presentation\Api\Dto\Output\ApprovalRequestOutput;
use Approval\Presentation\Api\Factory\ApprovalRequestOutputFactory;
use Approval\Presentation\Api\Provider\ListApprovalRequestsProvider;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
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
 * Test ListApprovalRequestsProviderTest.
 *
 * Pending approvals are the four-eyes queue: the organization comes from the
 * URI (never from a caller-supplied filter), and the page size is clamped so
 * the queue cannot be dumped wholesale.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListApprovalRequestsProvider::class)]
final class ListApprovalRequestsProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655503001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655503002';

  private const string REQUEST_ID = '550e8400-e29b-41d4-a716-446655503003';

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function missingOrganizationProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank organizationId' => [['organizationId' => '']];
    yield 'non-string organizationId' => [['organizationId' => 42]];
  }

  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'organization access denied' => [
      OrganizationAccessDeniedException::missingPermission('organization.approval.read'),
      AccessDeniedHttpException::class,
    ];
    yield 'request not found' => [
      ApprovalRequestNotFoundException::withId(self::REQUEST_ID),
      NotFoundHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Unknown status filter.'),
      BadRequestHttpException::class,
    ];
  }

  /**
   * @return iterable<string, array{array<string, int>, int, int}>
   */
  public static function paginationProvider(): iterable
  {
    yield 'defaults' => [[], 1, 30];
    yield 'explicit window' => [['page' => 4, 'itemsPerPage' => 25], 4, 25];
    yield 'page below one is clamped' => [['page' => -3], 1, 30];
    yield 'items per page is capped at one hundred' => [['itemsPerPage' => 999], 1, 100];
  }

  #[Test]
  public function testItReturnsThePageOfApprovalRequests(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListApprovalRequestsQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId
        && self::USER_ID === $query->userId
        && null === $query->status
        && null === $query->actionType))
      ->willReturn(new ListApprovalRequestsResult(items: [$this->view()], page: 1, itemsPerPage: 30, total: 1));

    $output = $this->createProvider($queryBus, [])
      ->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(1.0, $output->getTotalItems());

    $items = iterator_to_array($output);
    self::assertCount(1, $items);

    $item = $items[0];
    self::assertInstanceOf(ApprovalRequestOutput::class, $item);
    self::assertSame(self::REQUEST_ID, $item->id);
    self::assertSame('pending', $item->status);
    self::assertSame('equipment_decommission', $item->actionType);
  }

  #[Test]
  public function testItForwardsTheStatusAndActionTypeFilters(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListApprovalRequestsQuery $query): bool => 'pending' === $query->status
        && 'nc_waiver' === $query->actionType))
      ->willReturn(new ListApprovalRequestsResult(items: [], page: 1, itemsPerPage: 30, total: 0));

    $this->createProvider($queryBus, ['status' => 'pending', 'actionType' => 'nc_waiver'])
      ->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testItDropsBlankFilters(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListApprovalRequestsQuery $query): bool => null === $query->status
        && null === $query->actionType))
      ->willReturn(new ListApprovalRequestsResult(items: [], page: 1, itemsPerPage: 30, total: 0));

    $this->createProvider($queryBus, ['status' => '', 'actionType' => ''])
      ->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  /**
   * @param array<string, int> $query
   */
  #[Test]
  #[DataProvider('paginationProvider')]
  public function testItClampsThePaginationWindow(array $query, int $expectedPage, int $expectedItemsPerPage): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListApprovalRequestsQuery $listQuery): bool => $expectedPage === $listQuery->page
        && $expectedItemsPerPage === $listQuery->itemsPerPage))
      ->willReturn(new ListApprovalRequestsResult(items: [], page: $expectedPage, itemsPerPage: $expectedItemsPerPage, total: 0));

    $this->createProvider($queryBus, $query)
      ->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListApprovalRequestsProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $this->requestStack([]),
      outputFactory: new ApprovalRequestOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('missingOrganizationProvider')]
  public function testItRefusesAListingWithoutAnOrganizationUriVariable(array $uriVariables): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($queryBus, [])->provide(new GetCollection(), $uriVariables);
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

    $this->createProvider($queryBus, [])->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testItRethrowsAnUnrecognisedFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('database is down'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('database is down');

    $this->createProvider($queryBus, [])->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  /**
   * @param array<string, int|string> $query
   */
  private function createProvider(QueryBusPort $queryBus, array $query): ListApprovalRequestsProvider
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

    return new ListApprovalRequestsProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $this->requestStack($query),
      outputFactory: new ApprovalRequestOutputFactory(),
    );
  }

  /**
   * @param array<string, int|string> $query
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

  private function view(): GetApprovalRequestResult
  {
    return new GetApprovalRequestResult(
      id: self::REQUEST_ID,
      organizationId: self::ORGANIZATION_ID,
      actionType: 'equipment_decommission',
      subjectId: '550e8400-e29b-41d4-a716-446655503004',
      status: 'pending',
      requestedByMemberId: 'member-1',
      requestedByUserId: self::USER_ID,
      decisionByMemberId: null,
      decisionByUserId: null,
      decisionNote: null,
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
      decidedAt: null,
      executedAt: null,
      executionError: null,
    );
  }
}
