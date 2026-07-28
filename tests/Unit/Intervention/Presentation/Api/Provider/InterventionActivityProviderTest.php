<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowPage, InterventionWorkflowView};
use Intervention\Application\UseCase\Query\Activity\ListInterventionActivities\{ListInterventionActivitiesQuery, ListInterventionActivitiesResult};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Presentation\Api\Dto\Output\InterventionActivityOutput;
use Intervention\Presentation\Api\Factory\InterventionActivityOutputFactory;
use Intervention\Presentation\Api\Provider\InterventionActivityProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function iterator_to_array;

/**
 * Test InterventionActivityProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionActivityProvider::class)]
final class InterventionActivityProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441500';

  private const string ACTIVITY_ID = '550e8400-e29b-41d4-a716-446655441800';

  // #region Methods
  #[Test]
  public function testProvideListsActivitiesAndClampsThePagination(): void
  {
    $requestStack = $this->requestStack('?page=0&itemsPerPage=1000');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInterventionActivitiesQuery $query): bool {
        self::assertSame(self::USER_ID, $query->userId);
        self::assertSame(self::INTERVENTION_ID, $query->interventionId);
        self::assertSame(1, $query->page);
        self::assertSame(100, $query->itemsPerPage);

        return true;
      }))
      ->willReturn(new ListInterventionActivitiesResult(new InterventionWorkflowPage([$this->view()], 1, 100, 1)));

    $paginator = $this->provider($queryBus, $requestStack)
      ->provide(new GetCollection(), ['interventionId' => self::INTERVENTION_ID]);

    self::assertInstanceOf(TraversablePaginator::class, $paginator);

    $items = iterator_to_array($paginator);
    self::assertCount(1, $items);
    self::assertInstanceOf(InterventionActivityOutput::class, $items[0]);
    self::assertSame(self::ACTIVITY_ID, $items[0]->id);
    self::assertSame('comment', $items[0]->kind);
  }

  #[Test]
  public function testProvideRequiresTheInterventionIdUriVariable(): void
  {
    $provider = $this->provider($this->createStub(QueryBusPort::class), $this->requestStack(''));

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideRequiresAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new InterventionActivityProvider(
      $this->createStub(QueryBusPort::class),
      new InterventionActivityOutputFactory(),
      $security,
      $this->requestStack(''),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['interventionId' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testProvideMapsADomainFailureToItsHttpEquivalent(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::INTERVENTION_ID));

    $provider = $this->provider($queryBus, $this->requestStack(''));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['interventionId' => self::INTERVENTION_ID]);
  }

  private function provider(QueryBusPort $queryBus, RequestStack $requestStack): InterventionActivityProvider
  {
    return new InterventionActivityProvider(
      $queryBus,
      new InterventionActivityOutputFactory(),
      $this->security(),
      $requestStack,
    );
  }

  private function requestStack(string $queryString): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/interventions/' . self::INTERVENTION_ID . '/activities' . $queryString));

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
    return new InterventionWorkflowView('activity', '550e8400-e29b-41d4-a716-446655440001', [
      'id' => self::ACTIVITY_ID,
      'intervention' => '/api/interventions/' . self::INTERVENTION_ID,
      'kind' => 'comment',
      'event' => 'comment',
      'actor' => null,
      'body' => 'Looks good.',
      'payload' => null,
      'createdAt' => '2026-01-01T00:00:00+00:00',
    ]);
  }
  // #endregion
}
