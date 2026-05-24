<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\GetFacilityChildren\{GetFacilityChildrenQuery, GetFacilityChildrenResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Presentation\Api\Provider\Facility\ListFacilityChildrenProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
      ->with(self::isInstanceOf(GetFacilityChildrenQuery::class))
      ->willReturn(new GetFacilityChildrenResult([
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
        ),
      ]));

    $provider = new ListFacilityChildrenProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->makeRequestStack(),
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId, 'facilityId' => $facilityId],
    );

    self::assertCount(1, $outputs);
    self::assertSame('Building A', $outputs[0]->name);
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
