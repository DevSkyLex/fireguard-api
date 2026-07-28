<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Console;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{
  AddOrganizationMemberCommand,
  AddOrganizationMemberResult
};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{
  OrganizationId,
  OrganizationName,
  OrganizationRoleId,
  OrganizationRoleName
};
use Organization\Infrastructure\Console\AddOrganizationAdminConsoleCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\ValueObject\Email;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

/**
 * Test AddOrganizationAdminConsoleCommand.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddOrganizationAdminConsoleCommand::class)]
final class AddOrganizationAdminConsoleCommandTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655440012';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440011';

  private const string USER_EMAIL = 'owner@example.com';
  // #endregion

  // #region Methods
  #[Test]
  public function testConfigureDeclaresBothArguments(): void
  {
    $definition = $this->createCommand($this->createStub(CommandBusPort::class))->getDefinition();

    self::assertTrue($definition->hasArgument('organization'));
    self::assertTrue($definition->hasArgument('user'));
  }

  #[Test]
  public function testAddsTheOwnerRoleWithoutEnforcingTheQuota(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof AddOrganizationMemberCommand
        && self::ORGANIZATION_ID === $command->organizationId
        && self::USER_ID === $command->userId
        && [self::ROLE_ID] === $command->roleIds
        && false === $command->enforceQuota))
      ->willReturn($this->successResult());

    $tester = new CommandTester($this->createCommand($commandBus));

    $exitCode = $tester->execute([
      'organization' => '  ' . self::ORGANIZATION_ID . '  ',
      'user' => '  ' . self::USER_ID . '  ',
    ]);

    $display = $tester->getDisplay();

    self::assertSame(Command::SUCCESS, $exitCode);
    self::assertStringContainsString(self::MEMBER_ID, $display);
    self::assertStringContainsString('Yes', $display);
  }

  #[Test]
  public function testResolvesTheUserByEmail(): void
  {
    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->willReturn($this->user());
    $userRepository->expects(self::never())->method('findById');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->willReturn($this->successResult());

    $tester = new CommandTester($this->createCommand($commandBus, userRepository: $userRepository));

    $exitCode = $tester->execute([
      'organization' => self::ORGANIZATION_ID,
      'user' => self::USER_EMAIL,
    ]);

    self::assertSame(Command::SUCCESS, $exitCode);
  }

  #[Test]
  public function testFailsWhenTheOrganizationArgumentIsBlank(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute(['organization' => '   ', 'user' => self::USER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Organization ID is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheUserArgumentIsBlank(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute(['organization' => self::ORGANIZATION_ID, 'user' => '   ']);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('User (ID or email) is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheOrganizationIdIsNotAUuid(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute(['organization' => 'not-a-uuid', 'user' => self::USER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Invalid organization ID', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheOrganizationDoesNotExist(): void
  {
    $tester = new CommandTester($this->createCommand(
      $this->neverDispatchingBus(),
      organization: null,
    ));

    $exitCode = $tester->execute(['organization' => self::ORGANIZATION_ID, 'user' => self::USER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('not found', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheUserCannotBeResolved(): void
  {
    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findByEmail')->willReturn(null);

    $tester = new CommandTester($this->createCommand(
      $this->neverDispatchingBus(),
      userRepository: $userRepository,
    ));

    $exitCode = $tester->execute(['organization' => self::ORGANIZATION_ID, 'user' => self::USER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Failed to resolve user', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheOwnerRoleIsMissing(): void
  {
    $tester = new CommandTester($this->createCommand(
      $this->neverDispatchingBus(),
      ownerRole: null,
    ));

    $exitCode = $tester->execute(['organization' => self::ORGANIZATION_ID, 'user' => self::USER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Owner role not found', $tester->getDisplay());
  }

  #[Test]
  public function testReportsAFailureWhenTheCommandBusThrows(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('already a member'));

    $tester = new CommandTester($this->createCommand($commandBus));

    $exitCode = $tester->execute(['organization' => self::ORGANIZATION_ID, 'user' => self::USER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('already a member', $tester->getDisplay());
  }

  private function neverDispatchingBus(): CommandBusPort
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    return $commandBus;
  }

  private function createCommand(
    CommandBusPort $commandBus,
    bool|Organization|null $organization = false,
    bool|OrganizationRole|null $ownerRole = false,
    ?UserRepositoryPort $userRepository = null,
  ): AddOrganizationAdminConsoleCommand {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(
      false === $organization ? $this->organization() : $organization,
    );

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findByOrganizationAndName')->willReturn(
      false === $ownerRole ? $this->ownerRole() : $ownerRole,
    );

    if (null === $userRepository) {
      $userRepository = $this->createStub(UserRepositoryPort::class);
      $userRepository->method('findById')->willReturn($this->user());
      $userRepository->method('findByEmail')->willReturn($this->user());
    }

    return new AddOrganizationAdminConsoleCommand(
      commandBus: $commandBus,
      organizationRepository: $organizationRepository,
      organizationRoleRepository: $roleRepository,
      userRepository: $userRepository,
    );
  }

  private function organization(): Organization
  {
    return Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Test'),
      ownerUserId: self::USER_ID,
    );
  }

  private function ownerRole(): OrganizationRole
  {
    return OrganizationRole::create(
      id: OrganizationRoleId::fromString(self::ROLE_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationRoleName('owner'),
      permissions: [],
      isSystem: true,
    );
  }

  private function user(): User
  {
    return User::register(
      id: new UserId(self::USER_ID),
      username: new Username('owner'),
      email: new Email(self::USER_EMAIL),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('Owner', 'User', null),
      eventIdProvider: new TestEventIdProvider(),
    );
  }

  private function successResult(): AddOrganizationMemberResult
  {
    return new AddOrganizationMemberResult(
      memberId: self::MEMBER_ID,
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      roleIds: [self::ROLE_ID],
      isActive: true,
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      wasCreatedOrReactivated: true,
    );
  }
  // #endregion
}
