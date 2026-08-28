<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionOutcome};
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Organization\Application\Port\Outbound\OrganizationRoleRepositoryPort;
use Organization\Application\Service\MemberInvitationProvisioningService;
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\{InviteOrganizationMemberCommand, InviteOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationMembershipConflictException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test MemberInvitationProvisioningServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MemberInvitationProvisioningService::class)]
final class MemberInvitationProvisioningServiceTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f69a01';

  private const string INVITED_BY = '018f0b68-6758-7a12-8a1d-3f0d97f69a02';

  private const string ROLE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f69a03';

  #[Test]
  public function itResolvesRoleNamesAndDispatchesTheExistingInviteCommand(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (InviteOrganizationMemberCommand $command) use (&$captured): InviteOrganizationMemberResult {
        $captured = $command;

        return $this->fakeResult();
      });

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn($this->role('admin'));

    $result = new MemberInvitationProvisioningService($commandBus, $roleRepository)
      ->provision($this->request(roleNames: ['admin']));

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertSame('invitation-1', $result->resourceId);
    self::assertInstanceOf(InviteOrganizationMemberCommand::class, $captured);
    self::assertSame([self::ROLE_ID], $captured->roleIds);
    self::assertSame(self::INVITED_BY, $captured->invitedByUserId);
  }

  #[Test]
  public function itResolvesTheDefaultMemberRoleWhenNoRoleNamesAreGiven(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->willReturn($this->fakeResult());

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->with(
        self::isInstanceOf(OrganizationId::class),
        self::callback(static fn (OrganizationRoleName $name): bool => 'member' === (string) $name),
      )
      ->willReturn($this->role('member'));

    $result = new MemberInvitationProvisioningService($commandBus, $roleRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
  }

  #[Test]
  public function itReturnsUnknownRoleWithoutDispatchingWhenARoleNameDoesNotExist(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findByOrganizationAndName')->willReturn(null);

    $result = new MemberInvitationProvisioningService($commandBus, $roleRepository)
      ->provision($this->request(roleNames: ['ghost']));

    self::assertSame(ProvisionOutcome::UNKNOWN_ROLE, $result->outcome);
    self::assertStringContainsString('ghost', (string) $result->message);
  }

  #[Test]
  public function itReturnsInvalidWithoutDispatchingForAMalformedEmail(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');

    $result = new MemberInvitationProvisioningService($commandBus, $roleRepository)
      ->provision($this->request(email: 'not-an-email'));

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertStringContainsString('not-an-email', (string) $result->message);
  }

  #[Test]
  public function itValidatesWithoutDispatchingOnADryRun(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn($this->role('admin'));

    $result = new MemberInvitationProvisioningService($commandBus, $roleRepository)
      ->provision($this->request(roleNames: ['admin'], dryRun: true));

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertNull($result->resourceId, 'A dry run creates nothing, so it carries no invitation id.');
  }

  #[Test]
  public function itDetectsAnUnknownRoleOnADryRunToo(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findByOrganizationAndName')->willReturn(null);

    $result = new MemberInvitationProvisioningService($commandBus, $roleRepository)
      ->provision($this->request(roleNames: ['ghost'], dryRun: true));

    self::assertSame(ProvisionOutcome::UNKNOWN_ROLE, $result->outcome);
  }

  #[Test]
  public function itMapsTheTwoMembershipConflictsToDistinctOutcomes(): void
  {
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findByOrganizationAndName')->willReturn($this->role('member'));

    $invited = $this->createStub(CommandBusPort::class);
    $invited->method('dispatch')->willThrowException(OrganizationMembershipConflictException::pendingInvitationExists());
    $resultInvited = new MemberInvitationProvisioningService($invited, $roleRepository)->provision($this->request());
    self::assertSame(ProvisionOutcome::ALREADY_INVITED, $resultInvited->outcome);

    $member = $this->createStub(CommandBusPort::class);
    $member->method('dispatch')->willThrowException(OrganizationMembershipConflictException::alreadyAnActiveMember());
    $resultMember = new MemberInvitationProvisioningService($member, $roleRepository)->provision($this->request());
    self::assertSame(ProvisionOutcome::ALREADY_MEMBER, $resultMember->outcome);
  }

  #[Test]
  public function itUnwrapsMessengerWrappedFailuresIntoTheirOutcomes(): void
  {
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findByOrganizationAndName')->willReturn($this->role('member'));

    $quota = $this->createStub(CommandBusPort::class);
    $quota->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(OrganizationQuotaExceededException::forResource('members', 5)),
    );
    self::assertSame(
      ProvisionOutcome::QUOTA_EXCEEDED,
      new MemberInvitationProvisioningService($quota, $roleRepository)->provision($this->request())->outcome,
    );

    $conflict = $this->createStub(CommandBusPort::class);
    $conflict->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(OrganizationMembershipConflictException::pendingInvitationExists()),
    );
    self::assertSame(
      ProvisionOutcome::ALREADY_INVITED,
      new MemberInvitationProvisioningService($conflict, $roleRepository)->provision($this->request())->outcome,
    );

    $role = $this->createStub(CommandBusPort::class);
    $role->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(OrganizationRoleNotFoundException::withName('ghost')),
    );
    self::assertSame(
      ProvisionOutcome::UNKNOWN_ROLE,
      new MemberInvitationProvisioningService($role, $roleRepository)->provision($this->request())->outcome,
    );
  }

  #[Test]
  public function itRethrowsAnUnrecognizedWrappedException(): void
  {
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findByOrganizationAndName')->willReturn($this->role('member'));

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('unexpected')),
    );

    $this->expectException(MessengerRuntimeException::class);

    new MemberInvitationProvisioningService($commandBus, $roleRepository)->provision($this->request());
  }

  /**
   * @param list<string> $roleNames
   */
  private function request(
    string $email = 'alice@example.com',
    array $roleNames = [],
    bool $dryRun = false,
  ): ProvisionMemberInvitationRequest {
    return new ProvisionMemberInvitationRequest(
      organizationId: self::ORGANIZATION_ID,
      email: $email,
      invitedByUserId: self::INVITED_BY,
      roleNames: $roleNames,
      dryRun: $dryRun,
    );
  }

  private function role(string $name): OrganizationRole
  {
    return OrganizationRole::create(
      id: OrganizationRoleId::fromString(self::ROLE_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationRoleName($name),
      permissions: [],
    );
  }

  private function fakeResult(): InviteOrganizationMemberResult
  {
    return new InviteOrganizationMemberResult(
      invitationId: 'invitation-1',
      organizationId: self::ORGANIZATION_ID,
      email: 'alice@example.com',
      status: 'pending',
      invitedByUserId: self::INVITED_BY,
      expiresAt: new DateTimeImmutable('2026-01-12T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-05T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-05T00:00:00+00:00'),
      roleIds: [self::ROLE_ID],
      acceptUrl: 'https://example.com/accept',
    );
  }
}
