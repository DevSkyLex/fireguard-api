<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\GetFacilityDescendants\{GetFacilityDescendantsQuery, GetFacilityDescendantsResult};
use Facility\Presentation\Api\Provider\Facility\ListFacilityDescendantsProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

#[CoversClass(ListFacilityDescendantsProvider::class)]
final class ListFacilityDescendantsProviderTest extends TestCase
{
  #[Test]
  public function testProvideMapsResults(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441300';
    $facilityId = '550e8400-e29b-41d4-a716-446655441301';

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441302'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetFacilityDescendantsQuery::class))
      ->willReturn(new GetFacilityDescendantsResult([
        new GetFacilityResult(
          facilityId: '550e8400-e29b-41d4-a716-446655441303',
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
        new GetFacilityResult(
          facilityId: '550e8400-e29b-41d4-a716-446655441304',
          organizationId: $organizationId,
          parentFacilityId: '550e8400-e29b-41d4-a716-446655441303',
          type: 'floor',
          name: 'Floor 1',
          code: null,
          status: 'active',
          address: null,
          metadata: [],
          createdAt: new DateTimeImmutable('2026-02-12T10:20:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-02-12T10:30:00+00:00'),
        ),
      ]));

    $provider = new ListFacilityDescendantsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->makeRequestStack(),
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId, 'facilityId' => $facilityId],
    );

    self::assertCount(2, $outputs);
    self::assertSame('Floor 1', $outputs[1]->name);
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
