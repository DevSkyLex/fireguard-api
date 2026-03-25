<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember\{RemoveOrganizationMemberCommand, RemoveOrganizationMemberHandler, RemoveOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveOrganizationMemberHandler::class)]
final class RemoveOrganizationMemberHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655470001';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655470002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655470003';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655470099';

  // #region Methods
  #[Test]
  public function testInvokeDeactivatesMemberWhenMemberBelongsToOrganization(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::once())->method('save')->with($member);

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new RemoveOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));

    self::assertInstanceOf(RemoveOrganizationMemberResult::class, $result);
    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertFalse($member->isActive());
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findById');
    $memberRepository->expects(self::never())->method('save');

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new RemoveOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberNotFound(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn(null);
    $memberRepository->expects(self::never())->method('save');

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new RemoveOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberDoesNotBelongToOrganization(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $memberFromAnotherOrg = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::OTHER_ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($memberFromAnotherOrg);
    $memberRepository->expects(self::never())->method('save');

    $handler = new RemoveOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new RemoveOrganizationMemberCommand(
      organizationId: self::ORG_ID,
      memberId: self::MEMBER_ID,
    ));
  }
  // #endregion
}
