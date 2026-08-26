<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\InspectionResponse;

use ApiPlatform\Metadata\{Get, GetCollection};
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\UseCase\Query\Response\GetInspectionResponse\GetInspectionResponseResult;
use Inspection\Application\UseCase\Query\Response\ListInspectionResponses\{ListInspectionResponsesQuery, ListInspectionResponsesResult};
use Inspection\Application\UseCase\Query\Response\ResolveInspectionResponseScope\{ResolveInspectionResponseScopeQuery, ResolveInspectionResponseScopeResult};
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Inspection\Presentation\Api\Provider\InspectionResponse\InspectionResponseProvider;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\{QueryMessage, ResultMessage};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function array_values;
use function iterator_to_array;

/**
 * Test InspectionResponseProviderTest.
 *
 * The provider no longer writes DQL, so what is pinned here is what it still
 * owns: the view-to-output projection, the gate and its 404-not-403 split,
 * the IRI filters it parses, and the pagination clamp. The collection's
 * actual filtering is exercised against PostgreSQL in the integration test.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionResponseProvider::class)]
final class InspectionResponseProviderTest extends TestCase
{
  // #region Constants
  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440009';
  // #endregion

  // #region Tests — the item read
  /**
   * Method testTheItemReadProjectsTheViewOntoIris.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheItemReadProjectsTheViewOntoIris(): void
  {
    $output = $this->provider($this->request())->provide(new Get(), ['id' => self::RESPONSE_ID]);

    self::assertInstanceOf(InspectionResponseOutput::class, $output);
    self::assertSame(self::RESPONSE_ID, $output->id);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $output->organization);
    self::assertSame('/api/inspections/' . self::INSPECTION_ID, $output->inspection);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $output->intervention);
    self::assertSame('draft', $output->recordStatus);
    self::assertSame(2, $output->revision);
    self::assertSame('pressure', $output->itemKey);
    self::assertSame(['ok' => true], $output->value);
    self::assertSame('2026-08-26T10:00:00+00:00', $output->createdAt);
  }

  /**
   * Method testTheItemReadLeavesTheInterventionNullWhenAbsent.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheItemReadLeavesTheInterventionNullWhenAbsent(): void
  {
    $output = $this->provider($this->request(), view: $this->view(interventionId: null))
      ->provide(new Get(), ['id' => self::RESPONSE_ID]);

    self::assertInstanceOf(InspectionResponseOutput::class, $output);
    self::assertNull($output->intervention);
  }

  /**
   * Method testAnUnknownResponseIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownResponseIsNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Inspection response not found.');

    $this->provider($this->request(), found: false)->provide(new Get(), ['id' => self::RESPONSE_ID]);
  }

  /**
   * Method testAResponseOutsideTheCallersScopeIsNotFoundRatherThanForbidden.
   *
   * A 403 would confirm the id exists in another tenant.
   *
   * @return void no return value
   */
  #[Test]
  public function testAResponseOutsideTheCallersScopeIsNotFoundRatherThanForbidden(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    $this->provider($this->request(), decision: OrganizationAccessDecision::OUTSIDE_SCOPE)
      ->provide(new Get(), ['id' => self::RESPONSE_ID]);
  }

  /**
   * Method testAMissingReadPermissionIsForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMissingReadPermissionIsForbidden(): void
  {
    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.inspection.read permission.');

    $this->provider($this->request(), decision: OrganizationAccessDecision::MISSING_PERMISSION)
      ->provide(new Get(), ['id' => self::RESPONSE_ID]);
  }

  /**
   * Method testAnUnauthenticatedCallerIsForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnauthenticatedCallerIsForbidden(): void
  {
    $this->expectException(AccessDeniedHttpException::class);

    $this->provider($this->request(), authenticated: false)->provide(new Get(), ['id' => self::RESPONSE_ID]);
  }
  // #endregion

  // #region Tests — the collection read
  /**
   * Method testAnUnresolvableScopeIsABadRequest.
   *
   * Both "no filter at all" and "a filter that names nothing" land here: an
   * intervention id that resolves to no intervention is a bad filter, not a
   * missing resource.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnresolvableScopeIsABadRequest(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The organization, intervention or inspection filter is required.');

    $this->provider($this->request(), scopeOrganizationId: null)->provide(new GetCollection(), []);
  }

  /**
   * Method testTheFiltersAreParsedFromIrisAndForwardedToTheQuery.
   *
   * An IRI is transport; the query carries identifiers.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheFiltersAreParsedFromIrisAndForwardedToTheQuery(): void
  {
    $bus = $this->recordingQueryBus();

    $this->provider($this->request([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'intervention' => '/api/interventions/' . self::INTERVENTION_ID,
      'inspection' => '/api/inspections/' . self::INSPECTION_ID,
      'recordStatus' => 'published',
    ]), queryBus: $bus)->provide(new GetCollection(), []);

    $scope = $bus->asked[0];
    self::assertInstanceOf(ResolveInspectionResponseScopeQuery::class, $scope);
    self::assertSame(self::ORGANIZATION_ID, $scope->organizationId);
    self::assertSame(self::INTERVENTION_ID, $scope->interventionId);
    self::assertSame(self::INSPECTION_ID, $scope->inspectionId);

    $list = $bus->asked[1];
    self::assertInstanceOf(ListInspectionResponsesQuery::class, $list);
    self::assertSame(self::ORGANIZATION_ID, $list->organizationId);
    self::assertSame(self::INTERVENTION_ID, $list->interventionId);
    self::assertSame(self::INSPECTION_ID, $list->inspectionId);
    self::assertSame('published', $list->recordStatus);
  }

  /**
   * Method testAnEmptyRecordStatusIsNoFilterAtAll.
   *
   * The default belongs to the handler, so the provider must forward null
   * rather than an empty string.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnEmptyRecordStatusIsNoFilterAtAll(): void
  {
    $bus = $this->recordingQueryBus();

    $this->provider($this->request([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'recordStatus' => '',
    ]), queryBus: $bus)->provide(new GetCollection(), []);

    $list = $bus->asked[1];
    self::assertInstanceOf(ListInspectionResponsesQuery::class, $list);
    self::assertNull($list->recordStatus);
    self::assertNull($list->interventionId);
  }

  /**
   * Method testThePaginationIsClampedToTheResourceBounds.
   *
   * @return void no return value
   */
  #[Test]
  public function testThePaginationIsClampedToTheResourceBounds(): void
  {
    $bus = $this->recordingQueryBus();
    $provider = $this->provider($this->request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]), queryBus: $bus);

    $provider->provide(new GetCollection(), [], ['filters' => ['page' => '0', 'itemsPerPage' => '5000']]);
    $clamped = $bus->asked[1];
    self::assertInstanceOf(ListInspectionResponsesQuery::class, $clamped);
    self::assertSame(1, $clamped->page);
    self::assertSame(100, $clamped->itemsPerPage);

    $provider->provide(new GetCollection(), [], ['filters' => ['page' => 'first', 'itemsPerPage' => 'many']]);
    $defaults = $bus->asked[3];
    self::assertInstanceOf(ListInspectionResponsesQuery::class, $defaults);
    self::assertSame(1, $defaults->page);
    self::assertSame(50, $defaults->itemsPerPage);
  }

  /**
   * Method testTheCollectionAnswersAPaginatorOfOutputs.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheCollectionAnswersAPaginatorOfOutputs(): void
  {
    $page = $this->provider($this->request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]))
      ->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $page);
    self::assertSame(7.0, $page->getTotalItems());
    $outputs = array_values(iterator_to_array($page));
    self::assertCount(1, $outputs);
    self::assertInstanceOf(InspectionResponseOutput::class, $outputs[0]);
    self::assertSame(self::RESPONSE_ID, $outputs[0]->id);
  }

  /**
   * Method organizationId.
   *
   * @static
   *
   * Exposed so the anonymous query bus above can reach the constant.
   *
   * @return string the seeded organization identifier
   */
  public static function organizationId(): string
  {
    return self::ORGANIZATION_ID;
  }
  // #endregion

  // #region Helpers
  /**
   * Method provider.
   *
   * @param RequestStack $requestStack the request stack
   * @param ?InspectionResponseView $view the view the query bus answers with
   * @param bool $found whether the item query finds the row
   * @param ?string $scopeOrganizationId the organization the scope query resolves
   * @param OrganizationAccessDecision $decision the authorization answer
   * @param bool $authenticated whether a SecurityUser is present
   * @param ?QueryBusPort $queryBus an explicit query bus
   *
   * @return InspectionResponseProvider the provider under test
   */
  private function provider(
    RequestStack $requestStack,
    ?InspectionResponseView $view = null,
    bool $found = true,
    ?string $scopeOrganizationId = self::ORGANIZATION_ID,
    OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED,
    bool $authenticated = true,
    ?QueryBusPort $queryBus = null,
  ): InspectionResponseProvider {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($authenticated
      ? new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true)
      : null);

    return new InspectionResponseProvider(
      $queryBus ?? $this->queryBus($found ? $view ?? $this->view() : null, $scopeOrganizationId),
      $authorization,
      $security,
      $requestStack,
    );
  }

  /**
   * Method queryBus.
   *
   * @param ?InspectionResponseView $view the view the item and list queries answer with
   * @param ?string $scopeOrganizationId the organization the scope query resolves
   *
   * @return QueryBusPort a bus answering all three queries
   */
  private function queryBus(?InspectionResponseView $view, ?string $scopeOrganizationId): QueryBusPort
  {
    $bus = $this->createStub(QueryBusPort::class);
    $bus->method('ask')->willReturnCallback(
      static fn (QueryMessage $query): ResultMessage => match (true) {
        $query instanceof ResolveInspectionResponseScopeQuery => new ResolveInspectionResponseScopeResult($scopeOrganizationId),
        $query instanceof ListInspectionResponsesQuery => new ListInspectionResponsesResult(
          views: null === $view ? [] : [$view],
          page: $query->page,
          itemsPerPage: $query->itemsPerPage,
          total: 7,
        ),
        default => new GetInspectionResponseResult($view),
      },
    );

    return $bus;
  }

  /**
   * Method recordingQueryBus.
   *
   * @return QueryBusPort&object{asked: list<QueryMessage>} a bus that keeps what it was asked
   */
  private function recordingQueryBus(): QueryBusPort
  {
    $view = $this->view();

    return new class ($view) implements QueryBusPort {
      /**
       * @var list<QueryMessage>
       */
      public array $asked = [];

      public function __construct(private readonly InspectionResponseView $view)
      {
      }

      public function ask(QueryMessage $query): ResultMessage
      {
        $this->asked[] = $query;

        return match (true) {
          $query instanceof ResolveInspectionResponseScopeQuery => new ResolveInspectionResponseScopeResult(
            InspectionResponseProviderTest::organizationId(),
          ),
          $query instanceof ListInspectionResponsesQuery => new ListInspectionResponsesResult(
            views: [$this->view],
            page: $query->page,
            itemsPerPage: $query->itemsPerPage,
            total: 1,
          ),
          default => new GetInspectionResponseResult($this->view),
        };
      }
    };
  }

  /**
   * Method request.
   *
   * @param array<string, string> $query the query parameters
   *
   * @return RequestStack the stack holding the request
   */
  private function request(array $query = []): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(Request::create('/api/inspection-responses', 'GET', $query));

    return $stack;
  }

  /**
   * Method view.
   *
   * @param ?string $interventionId the intervention the response belongs to
   *
   * @return InspectionResponseView a draft response at revision 2
   */
  private function view(?string $interventionId = self::INTERVENTION_ID): InspectionResponseView
  {
    $now = new DateTimeImmutable('2026-08-26T10:00:00+00:00');

    return new InspectionResponseView(
      id: self::RESPONSE_ID,
      organizationId: self::ORGANIZATION_ID,
      interventionId: $interventionId,
      inspectionId: self::INSPECTION_ID,
      clientId: null,
      recordStatus: 'draft',
      revision: 2,
      itemKey: 'pressure',
      value: ['ok' => true],
      createdAt: $now,
      updatedAt: $now,
    );
  }
  // #endregion
}
