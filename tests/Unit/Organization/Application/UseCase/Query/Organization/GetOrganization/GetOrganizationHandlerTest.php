<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganization;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationCallerMembershipPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationCallerRoleResult, GetOrganizationHandler, GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOrganizationHandler::class)]
final class GetOrganizationHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsMappedOrganizationResult(): void
  {
    $createdAt = new DateTimeImmutable('2025-01-01T00:00:00+00:00');

    $organization = Organization::reconstitute(
      id: new OrganizationId('550e8400-e29b-41d4-a716-446655440700'),
      name: new OrganizationName('Fireguard Rennes'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: $createdAt,
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->with(self::isInstanceOf(OrganizationId::class))
      ->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(self::isInstanceOf(OrganizationId::class))
      ->willReturn(5);

    /** @var PlanRepositoryPort&MockObject $planRepository */
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->expects(self::once())->method('findDefault')->willReturn(null);

    /** @var OrganizationCallerMembershipPort&MockObject $callerMembership */
    $callerMembership = $this->createMock(OrganizationCallerMembershipPort::class);
    $callerMembership->expects(self::never())->method('isOwner');
    $callerMembership->expects(self::never())->method('findActiveCallerMembership');
    $callerMembership->expects(self::never())->method('resolveRoles');

    $handler = new GetOrganizationHandler($organizationRepository, $memberRepository, $planRepository, $callerMembership);

    $result = $handler->__invoke(new GetOrganizationQuery('550e8400-e29b-41d4-a716-446655440700'));

    self::assertInstanceOf(GetOrganizationResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655440700', $result->id);
    self::assertSame('Fireguard Rennes', $result->name);
    self::assertSame('550e8400-e29b-41d4-a716-446655440001', $result->createdByUserId);
    self::assertTrue($result->isActive);
    self::assertSame($createdAt, $result->createdAt);
    self::assertSame(5, $result->memberCount);
    // No callerUserId on the query: caller-membership fields stay unresolved,
    // matching every pre-existing caller of this query (plan change, logo upload).
    self::assertNull($result->isOwner);
    self::assertNull($result->roles);
  }

  #[Test]
  public function testInvokeResolvesCallerMembershipWhenCallerUserIdIsProvided(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440700';
    $callerUserId = '550e8400-e29b-41d4-a716-446655440001';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Rennes'),
      createdByUserId: $callerUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
    );

    $membership = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440710'),
      organizationId: new OrganizationId($organizationId),
      userId: $callerUserId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(1);

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findDefault')->willReturn(null);

    $role = new GetOrganizationCallerRoleResult(id: '550e8400-e29b-41d4-a716-446655440720', label: 'admin');

    /** @var OrganizationCallerMembershipPort&MockObject $callerMembership */
    $callerMembership = $this->createMock(OrganizationCallerMembershipPort::class);
    $callerMembership->expects(self::once())
      ->method('isOwner')
      ->with($callerUserId, $callerUserId)
      ->willReturn(true);
    $callerMembership->expects(self::once())
      ->method('findActiveCallerMembership')
      ->with(self::isInstanceOf(OrganizationId::class), $callerUserId)
      ->willReturn($membership);
    $callerMembership->expects(self::once())
      ->method('resolveRoles')
      ->with(self::isInstanceOf(OrganizationId::class), $membership)
      ->willReturn([$role]);

    $handler = new GetOrganizationHandler($organizationRepository, $memberRepository, $planRepository, $callerMembership);

    $result = $handler->__invoke(new GetOrganizationQuery($organizationId, callerUserId: $callerUserId));

    self::assertTrue($result->isOwner);
    self::assertSame([$role], $result->roles);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $callerMembership = $this->createStub(OrganizationCallerMembershipPort::class);

    $handler = new GetOrganizationHandler($organizationRepository, $memberRepository, $planRepository, $callerMembership);

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationQuery('550e8400-e29b-41d4-a716-446655440701'));
  }
}
