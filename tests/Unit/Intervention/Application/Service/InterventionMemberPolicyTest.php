<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterventionMemberPolicyTest extends TestCase
{
  private const ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const OTHER_ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  private const USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itAcceptsTheActiveResponsibleMember(): void
  {
    $member = $this->member(self::ORGANIZATION_ID);
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($member);

    new InterventionMemberPolicy($repository)->assertResponsible(
      self::ORGANIZATION_ID,
      self::USER_ID,
      self::MEMBER_ID,
    );

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itRejectsAResponsibleMemberFromAnotherOrganization(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findById')->willReturn($this->member(self::OTHER_ORGANIZATION_ID));

    $this->expectException(InterventionConflictException::class);

    new InterventionMemberPolicy($repository)->assertActiveMember(self::ORGANIZATION_ID, self::MEMBER_ID);
  }

  #[Test]
  public function itRejectsSubmissionByAnotherMember(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Only the responsible member can submit the intervention.');

    new InterventionMemberPolicy($repository)->assertResponsible(
      self::ORGANIZATION_ID,
      self::USER_ID,
      '018f0b68-6758-7a12-8a1d-3f0d97f63c15',
    );
  }

  #[Test]
  public function itAllowsTheAssignedMemberToExecuteAWorkItem(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    new InterventionMemberPolicy($repository)->assertCanExecuteWorkItem(
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
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    $this->expectException(InterventionAccessDeniedException::class);
    $this->expectExceptionMessage('Only the assigned member can execute this work item.');

    new InterventionMemberPolicy($repository)->assertCanExecuteWorkItem(
      self::ORGANIZATION_ID,
      self::USER_ID,
      self::MEMBER_ID,
      [self::MEMBER_ID],
      '018f0b68-6758-7a12-8a1d-3f0d97f63c15',
    );
  }

  #[Test]
  public function itAllowsAInterventionParticipantToMutateInterventionResources(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    new InterventionMemberPolicy($repository)->assertCanExecuteIntervention(
      self::ORGANIZATION_ID,
      self::USER_ID,
      null,
      [self::MEMBER_ID],
    );

    self::addToAssertionCount(1);
  }

  #[Test]
  public function itRejectsANonParticipantMutatingInterventionResources(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    $this->expectException(InterventionAccessDeniedException::class);

    new InterventionMemberPolicy($repository)->assertCanExecuteIntervention(
      self::ORGANIZATION_ID,
      self::USER_ID,
      null,
      [],
    );
  }

  #[Test]
  public function itResolvesTheActiveMemberIdForAnAuthenticatedUser(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));
    $repository->method('findById')->willReturn($this->member(self::ORGANIZATION_ID));

    $memberId = new InterventionMemberPolicy($repository)->assertActiveMemberForUser(
      self::ORGANIZATION_ID,
      self::USER_ID,
    );

    self::assertSame(self::MEMBER_ID, $memberId);
  }

  #[Test]
  public function itRejectsAUserWithNoMembershipInTheOrganization(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn(null);

    $this->expectException(InterventionConflictException::class);

    new InterventionMemberPolicy($repository)->assertActiveMemberForUser(
      self::ORGANIZATION_ID,
      self::USER_ID,
    );
  }

  #[Test]
  public function itFindsTheMemberIdWithoutThrowingWhenActive(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member(self::ORGANIZATION_ID));

    $memberId = new InterventionMemberPolicy($repository)->findMemberId(self::ORGANIZATION_ID, self::USER_ID);

    self::assertSame(self::MEMBER_ID, $memberId);
  }

  #[Test]
  public function itFindsNoMemberIdWhenTheUserIsNotAMember(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn(null);

    $memberId = new InterventionMemberPolicy($repository)->findMemberId(self::ORGANIZATION_ID, self::USER_ID);

    self::assertNull($memberId);
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
