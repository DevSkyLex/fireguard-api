<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\GetFacilityChildren\GetFacilityChildrenQuery;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Presentation\Api\Provider\Facility\ListFacilityChildrenProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function iterator_to_array;

#[CoversClass(ListFacilityChildrenProvider::class)]
final class ListFacilityChildrenProviderTest extends TestCase
{
  #[Test]
  public function testProvideMapsResults(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441290';
    $facilityId = '550e8400-e29b-41d4-a716-446655441291';

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441292'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetFacilityChildrenQuery $query) use ($organizationId, $facilityId): bool {
        return $organizationId === $query->organizationId
          && $facilityId === $query->facilityId
          && 10 === $query->pagination->limit
          && 10 === $query->pagination->offset;
      }))
      ->willReturn(new PaginatedResult(
        items: [
          new GetFacilityResult(
            facilityId: '550e8400-e29b-41d4-a716-446655441293',
            organizationId: $organizationId,
            parentFacilityId: $facilityId,
            type: 'building',
            name: 'Building A',
            code: null,
            status: 'active',
            address: null,
            metadata: [],
            createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-02-12T10:10:00+00:00'),
            hasChildren: true,
            latitude: 48.8566,
            longitude: 2.3522,
          ),
        ],
        total: 11,
        limit: 10,
        offset: 10,
      ));

    $request = new Request();
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListFacilityChildrenProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId, 'facilityId' => $facilityId],
      context: ['filters' => ['page' => 2, 'itemsPerPage' => 10]],
    );

    self::assertInstanceOf(TraversablePaginator::class, $outputs);
    self::assertSame(11.0, $outputs->getTotalItems());

    $items = iterator_to_array($outputs);
    self::assertCount(1, $items);
    self::assertSame('Building A', $items[0]->name);
    self::assertTrue($items[0]->hasChildren);
    // The tree endpoints returned the DTO without coordinates while declaring
    // them, so a facility read through the tree looked un-located.
    self::assertSame(48.8566, $items[0]->latitude);
    self::assertSame(2.3522, $items[0]->longitude);
  }

  #[Test]
  public function testProvideMapsWrappedNotFoundToHttp404(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441294'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441295'),
    ));

    $provider = new ListFacilityChildrenProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->makeRequestStack(),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441296', 'facilityId' => '550e8400-e29b-41d4-a716-446655441295'],
    );
  }

  private function makeRequestStack(): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(new Request());

    return $stack;
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
}
