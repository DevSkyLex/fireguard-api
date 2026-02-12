<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\ListFacilities\{ListFacilitiesQuery, ListFacilitiesResult};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Provider\Facility\ListFacilitiesProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

#[CoversClass(ListFacilitiesProvider::class)]
final class ListFacilitiesProviderTest extends TestCase
{
  #[Test]
  public function testProvideUsesIncludeArchivedFalseByDefault(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441200';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441201');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListFacilitiesQuery $query) use ($organizationId): bool {
        return $organizationId === $query->organizationId
          && false === $query->includeArchived;
      }))
      ->willReturn(new ListFacilitiesResult([
        new GetFacilityResult(
          facilityId: '550e8400-e29b-41d4-a716-446655441202',
          organizationId: $organizationId,
          parentFacilityId: null,
          type: 'site',
          name: 'HQ',
          code: 'SITE-001',
          status: 'active',
          address: '10 rue',
          metadata: ['k' => 'v'],
          createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-02-12T10:30:00+00:00'),
        ),
      ]));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListFacilitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertCount(1, $outputs);
    self::assertInstanceOf(FacilityOutput::class, $outputs[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441202', $outputs[0]->id);
  }

  #[Test]
  public function testProvideUsesIncludeArchivedTrueWhenQueryParamIsTrue(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441210';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441211');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListFacilitiesQuery $query) use ($organizationId): bool {
        return $organizationId === $query->organizationId
          && true === $query->includeArchived;
      }))
      ->willReturn(new ListFacilitiesResult([]));

    $request = new Request();
    $request->query->set('includeArchived', 'true');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListFacilitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertCount(0, $outputs);
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
