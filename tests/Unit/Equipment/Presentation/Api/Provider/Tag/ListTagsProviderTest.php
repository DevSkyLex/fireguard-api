<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Tag;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Tag\ListTags\{ListTagsQuery, ListTagsResult};
use Equipment\Presentation\Api\Dto\Output\Equipment\TagOutput;
use Equipment\Presentation\Api\Provider\Tag\ListTagsProvider;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

use function iterator_to_array;

#[CoversClass(ListTagsProvider::class)]
final class ListTagsProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655460001';

  #[Test]
  public function testProvideThrowsAccessDeniedWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListTagsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenOrganizationIdMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655460010');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $provider = new ListTagsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
    );
  }

  #[Test]
  public function testProvideThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655460011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.read')
      ->willReturn(false);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListTagsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideMapsWrappedInvalidArgumentToBadRequest(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655460012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new ListTagsQuery(organizationId: 'bad-id', search: null, pagination: new Pagination(offset: 0, limit: 30))),
      [new InvalidArgumentException('Invalid organization id.')],
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new ListTagsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideReturnsEmptyPaginatorWhenNoTags(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655460013');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListTagsQuery::class))
      ->willReturn(new ListTagsResult(tags: [], total: 0));

    $provider = new ListTagsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $paginator = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
    self::assertSame(0.0, $paginator->getTotalItems());
  }

  #[Test]
  public function testProvideMapsResultToTagOutputPaginator(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655460014');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $tagId = '550e8400-e29b-41d4-a716-446655460099';

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListTagsQuery $q): bool {
        return 'extinguisher' === $q->search;
      }))
      ->willReturn(new ListTagsResult(
        tags: [['id' => $tagId, 'name' => 'extinguisher', 'organizationId' => self::ORG_ID]],
        total: 1,
      ));

    $request = new Request();
    $request->query->set('search', 'extinguisher');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListTagsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $paginator = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
    self::assertSame(1.0, $paginator->getTotalItems());

    $items = iterator_to_array($paginator);
    self::assertCount(1, $items);
    self::assertInstanceOf(TagOutput::class, $items[0]);
    self::assertSame($tagId, $items[0]->id);
    self::assertSame('extinguisher', $items[0]->name);
    self::assertSame(self::ORG_ID, $items[0]->organizationId);
  }

  #[Test]
  public function testProvideForwardsSearchNullWhenParamAbsent(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655460015');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListTagsQuery $q): bool {
        return null === $q->search;
      }))
      ->willReturn(new ListTagsResult(tags: [], total: 0));

    $provider = new ListTagsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideMapsADirectInvalidArgumentToBadRequest(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655460020');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InvalidArgumentException('Invalid organization id.'));

    $provider = new ListTagsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid organization id.');

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideRethrowsAWrappedFailureThatIsNotAnInvalidArgument(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655460021');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new ListTagsQuery(organizationId: self::ORG_ID, search: null, pagination: new Pagination(offset: 0, limit: 30))),
      [new RuntimeException('database is down')],
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new ListTagsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    // An infrastructure failure must surface as a 500, never be masked as a 400.
    $this->expectException(MessengerRuntimeException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function buildRequestStack(): RequestStack
  {
    $request = Request::create('/api/organizations/org-id/equipment/tags', 'GET');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    return $requestStack;
  }
}
