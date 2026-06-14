<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Application\Service;

use DateTimeImmutable;
use Mission\Application\Service\MissionMemberPolicy;
use Mission\Domain\Exception\{MissionAccessDeniedException, MissionConflictException};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MissionMemberPolicyTest extends TestCase
{
  private const ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const OTHER_ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  private const USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itAcceptsTheActiveResponsibleMember(): void
  {
    $member = $this->member(self::ORGANIZATION_ID);
    $repository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($member);

    new MissionMemberPolicy($repository)->assertResponsible(
      self::ORGANIZATION_ID,
      self::USER_ID,
      self::MEMBER_ID,
    );

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itRejectsAResponsibleMemberFromAnotherOrganization(): void
  {
    $repository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $repository->method('findById')->willReturn($this->member(self::OTHER_ORGANIZATION_ID));

    $this->expectException(MissionConflictException::class);

    new MissionMemberPolicy($repository)->assertActiveMember(self::ORGANIZATION_ID, self::MEMBER_ID);
  }

  #[Test]
  public function itRejectsSubmissionByAnotherMember(): void
  {
    $repository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    $this->expectException(MissionConflictException::class);
    $this->expectExceptionMessage('Only the responsible member can submit the mission.');

    new MissionMemberPolicy($repository)->assertResponsible(
      self::ORGANIZATION_ID,
      self::USER_ID,
      '018f0b68-6758-7a12-8a1d-3f0d97f63c15',
    );
  }

  #[Test]
  public function itAllowsTheAssignedMemberToExecuteAWorkItem(): void
  {
    $repository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    new MissionMemberPolicy($repository)->assertCanExecuteWorkItem(
      self::ORGANIZATION_ID,
      self::USER_ID,
      null,
      [],
      self::MEMBER_ID,
    );

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itRejectsExecutionOfAnotherMembersAssignedWorkItem(): void
  {
    $repository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    $this->expectException(MissionAccessDeniedException::class);
    $this->expectExceptionMessage('Only the assigned member can execute this work item.');

    new MissionMemberPolicy($repository)->assertCanExecuteWorkItem(
      self::ORGANIZATION_ID,
      self::USER_ID,
      self::MEMBER_ID,
      [self::MEMBER_ID],
      '018f0b68-6758-7a12-8a1d-3f0d97f63c15',
    );
  }

  #[Test]
  public function itAllowsAMissionParticipantToMutateMissionResources(): void
  {
    $repository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    new MissionMemberPolicy($repository)->assertCanExecuteMission(
      self::ORGANIZATION_ID,
      self::USER_ID,
      null,
      [self::MEMBER_ID],
    );

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itRejectsANonParticipantMutatingMissionResources(): void
  {
    $repository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    $this->expectException(MissionAccessDeniedException::class);

    new MissionMemberPolicy($repository)->assertCanExecuteMission(
      self::ORGANIZATION_ID,
      self::USER_ID,
      null,
      [],
    );
  }

  private function member(string $organizationId): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString($organizationId),
      self::USER_ID,
      true,
      new DateTimeImmutable(),
    );
  }
}
