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
use Intervention\Presentation\Api\Dto\Output\InterventionWorkItemOutput;
use Intervention\Presentation\Api\Factory\InterventionWorkItemOutputFactory;
use Intervention\Presentation\Api\Provider\InterventionWorkItemProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test InterventionWorkItemProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionWorkItemProvider::class)]
final class InterventionWorkItemProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441500';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441501';

  private const string WORK_ITEM_ID = '550e8400-e29b-41d4-a716-446655442000';

  // #region Methods
  #[Test]
  public function testProvideReturnsASingleWorkItemForAnItemRequest(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetInterventionWorkflowQuery $query): bool => 'work_item' === $query->resource
        && self::WORK_ITEM_ID === $query->id))
      ->willReturn(new GetInterventionWorkflowResult($this->view()));

    $output = $this->provider($queryBus, $this->requestStack(''))->provide(new Get(), ['id' => self::WORK_ITEM_ID]);

    self::assertInstanceOf(InterventionWorkItemOutput::class, $output);
    self::assertSame(self::WORK_ITEM_ID, $output->id);
  }

  #[Test]
  public function testProvideTranslatesTheAssigneeIriAndDropsEmptyFilters(): void
  {
    $requestStack = $this->requestStack(
      '?intervention=/api/interventions/' . self::INTERVENTION_ID
      . '&status=planned'
      . '&action='
      . '&assignee=/api/organizations/' . self::ORG_ID . '/members/' . self::MEMBER_ID,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInterventionWorkflowQuery $query): bool {
        self::assertSame('work_item', $query->resource);
        self::assertSame(self::INTERVENTION_ID, $query->scopeId);
        self::assertSame(['status' => 'planned', 'assigneeId' => self::MEMBER_ID], $query->filters);

        return true;
      }))
      ->willReturn(new ListInterventionWorkflowResult(new InterventionWorkflowPage([$this->view()], 1, 30, 1)));

    $paginator = $this->provider($queryBus, $requestStack)->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
  }

  #[Test]
  public function testProvideRequiresTheInterventionFilter(): void
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

    $provider = new InterventionWorkItemProvider(
      $this->createStub(QueryBusPort::class),
      new InterventionWorkItemOutputFactory($this->createStub(QueryBusPort::class)),
      $security,
      $this->requestStack(''),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => self::WORK_ITEM_ID]);
  }

  #[Test]
  public function testProvideMapsADomainFailureOnAnItemRequest(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::WORK_ITEM_ID));

    $provider = $this->provider($queryBus, $this->requestStack(''));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => self::WORK_ITEM_ID]);
  }

  #[Test]
  public function testProvideMapsADomainFailureOnACollectionRequest(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::INTERVENTION_ID));

    $provider = $this->provider(
      $queryBus,
      $this->requestStack('?intervention=/api/interventions/' . self::INTERVENTION_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection());
  }

  private function provider(QueryBusPort $queryBus, RequestStack $requestStack): InterventionWorkItemProvider
  {
    return new InterventionWorkItemProvider(
      $queryBus,
      new InterventionWorkItemOutputFactory($this->createStub(QueryBusPort::class)),
      $this->security(),
      $requestStack,
    );
  }

  private function requestStack(string $queryString): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-work-items' . $queryString));

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
    return new InterventionWorkflowView('work_item', self::ORG_ID, [
      'id' => self::WORK_ITEM_ID,
      'intervention' => '/api/interventions/' . self::INTERVENTION_ID,
      'action' => 'inspect',
      'target' => null,
      'resultResource' => null,
      'assignee' => null,
      'source' => 'planned',
      'status' => 'planned',
      'required' => true,
      'skipReason' => null,
      'evidenceCount' => 0,
      'revision' => 1,
      'createdAt' => '2026-01-01T00:00:00+00:00',
      'updatedAt' => '2026-01-01T00:00:00+00:00',
    ]);
  }
  // #endregion
}
