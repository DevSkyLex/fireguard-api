<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\{Get, GetCollection};
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowPage, InterventionWorkflowView};
use Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow\{GetInterventionWorkflowQuery, GetInterventionWorkflowResult};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\{ListInterventionWorkflowQuery, ListInterventionWorkflowResult};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use Intervention\Presentation\Api\Provider\InterventionProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test InterventionProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionProvider::class)]
final class InterventionProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441500';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441501';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655441502';

  // #region Methods
  #[Test]
  public function testProvideReturnsASingleInterventionForAnItemRequest(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetInterventionWorkflowQuery $query): bool => 'intervention' === $query->resource
        && self::INTERVENTION_ID === $query->id))
      ->willReturn(new GetInterventionWorkflowResult($this->view()));

    $output = $this->provider($queryBus, $this->requestStack(''))->provide(new Get(), ['id' => self::INTERVENTION_ID]);

    self::assertInstanceOf(InterventionOutput::class, $output);
    self::assertSame(self::INTERVENTION_ID, $output->id);
  }

  #[Test]
  public function testProvideTranslatesTheIriFiltersIntoIdentifiers(): void
  {
    $requestStack = $this->requestStack(
      '?organization=/api/organizations/' . self::ORG_ID
      . '&name=audit'
      . '&status='
      . '&responsible=/api/organizations/' . self::ORG_ID . '/members/' . self::MEMBER_ID
      . '&site=/api/facilities/' . self::SITE_ID,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInterventionWorkflowQuery $query): bool {
        self::assertSame('intervention', $query->resource);
        self::assertSame(self::ORG_ID, $query->scopeId);
        self::assertSame(
          ['name' => 'audit', 'responsibleId' => self::MEMBER_ID, 'siteId' => self::SITE_ID],
          $query->filters,
        );
        self::assertSame('updatedAt', $query->sorting->field);
        self::assertSame(SortDirection::DESC, $query->sorting->direction);

        return true;
      }))
      ->willReturn(new ListInterventionWorkflowResult(new InterventionWorkflowPage([$this->view()], 1, 30, 1)));

    $paginator = $this->provider($queryBus, $requestStack)->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
  }

  #[Test]
  public function testProvideForwardsTheOverdueDueFilter(): void
  {
    $requestStack = $this->requestStack(
      '?organization=/api/organizations/' . self::ORG_ID . '&due=overdue',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInterventionWorkflowQuery $query): bool {
        self::assertSame(['due' => 'overdue'], $query->filters);

        return true;
      }))
      ->willReturn(new ListInterventionWorkflowResult(new InterventionWorkflowPage([], 1, 30, 0)));

    $paginator = $this->provider($queryBus, $requestStack)->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
  }

  #[Test]
  public function testProvideRejectsAnUnknownDueValue(): void
  {
    $requestStack = $this->requestStack(
      '?organization=/api/organizations/' . self::ORG_ID . '&due=someday',
    );
    $provider = $this->provider($this->createStub(QueryBusPort::class), $requestStack);

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideRequiresTheOrganizationFilter(): void
  {
    $provider = $this->provider($this->createStub(QueryBusPort::class), $this->requestStack(''));

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideRequiresAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new InterventionProvider(
      $this->createStub(QueryBusPort::class),
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $this->requestStack(''),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testProvideMapsADomainFailureOnAnItemRequest(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::INTERVENTION_ID));

    $provider = $this->provider($queryBus, $this->requestStack(''));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testProvideMapsADomainFailureOnACollectionRequest(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::ORG_ID));

    $provider = $this->provider(
      $queryBus,
      $this->requestStack('?organization=/api/organizations/' . self::ORG_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection());
  }

  private function provider(QueryBusPort $queryBus, RequestStack $requestStack): InterventionProvider
  {
    return new InterventionProvider(
      $queryBus,
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $this->security(),
      $requestStack,
    );
  }

  private function requestStack(string $queryString): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/interventions' . $queryString));

    return $requestStack;
  }

  private function security(): Security
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

    return $security;
  }

  private function view(): InterventionWorkflowView
  {
    return new InterventionWorkflowView('intervention', self::ORG_ID, [
      'id' => self::INTERVENTION_ID,
      'organization' => '/api/organizations/' . self::ORG_ID,
      'number' => 1,
      'type' => 'site_setup',
      'name' => 'Mission',
      'description' => null,
      'status' => 'draft',
      'site' => null,
      'responsible' => null,
      'participants' => [],
      'priority' => 'normal',
      'plannedStartAt' => null,
      'dueAt' => null,
      'reviewNote' => null,
      'revision' => 1,
      'facilitiesCount' => 0,
      'equipmentCount' => 0,
      'inspectionsCount' => 0,
      'blockersCount' => 0,
      'workItemsCount' => 0,
      'completedWorkItemsCount' => 0,
      'proposedChangesCount' => 0,
      'commentsCount' => 0,
      'hasSignature' => false,
      'labels' => [],
      'createdAt' => '2026-01-01T00:00:00+00:00',
      'updatedAt' => '2026-01-01T00:00:00+00:00',
    ]);
  }
  // #endregion
}
