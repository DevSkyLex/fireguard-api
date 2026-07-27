<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Console;

use DateTimeImmutable;
use Organization\Application\UseCase\Command\Organization\CreateOrganization\{
  CreateOrganizationCommand,
  CreateOrganizationResult
};
use Organization\Infrastructure\Console\CreateOrganizationConsoleCommand;
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
 * Test CreateOrganizationConsoleCommand.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateOrganizationConsoleCommand::class)]
final class CreateOrganizationConsoleCommandTest extends TestCase
{
  // #region Constants
  private const string OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string OWNER_EMAIL = 'owner@example.com';
  // #endregion

  // #region Methods
  #[Test]
  public function testConfigureDeclaresArgumentsAndOptions(): void
  {
    $definition = $this->createCommand($this->createStub(CommandBusPort::class))->getDefinition();

    self::assertTrue($definition->hasArgument('name'));
    self::assertTrue($definition->hasArgument('owner'));
    self::assertTrue($definition->hasOption('slug'));
  }

  #[Test]
  public function testResolvesTheOwnerByUuidAndCreatesTheOrganization(): void
  {
    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (UserId $id): bool => self::OWNER_USER_ID === $id->value))
      ->willReturn($this->user());
    $userRepository->expects(self::never())->method('findByEmail');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof CreateOrganizationCommand
        && 'Acme Corp' === $command->name
        && self::OWNER_USER_ID === $command->ownerUserId
        && 'acme-corp' === $command->slug))
      ->willReturn($this->successResult());

    $tester = new CommandTester($this->createCommand($commandBus, $userRepository));

    $exitCode = $tester->execute([
      'name' => '  Acme Corp  ',
      'owner' => '  ' . self::OWNER_USER_ID . '  ',
      '--slug' => '  acme-corp  ',
    ]);

    $display = $tester->getDisplay();

    self::assertSame(Command::SUCCESS, $exitCode);
    self::assertStringContainsString(self::ORGANIZATION_ID, $display);
    self::assertStringContainsString('Acme Corp', $display);
  }

  #[Test]
  public function testResolvesTheOwnerByEmailAndDefaultsTheSlugToNull(): void
  {
    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->with(self::callback(static fn (Email $email): bool => self::OWNER_EMAIL === $email->value))
      ->willReturn($this->user());
    $userRepository->expects(self::never())->method('findById');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof CreateOrganizationCommand
        && null === $command->slug))
      ->willReturn($this->successResult());

    $tester = new CommandTester($this->createCommand($commandBus, $userRepository));

    $exitCode = $tester->execute([
      'name' => 'Acme Corp',
      'owner' => self::OWNER_EMAIL,
      '--slug' => '   ',
    ]);

    self::assertSame(Command::SUCCESS, $exitCode);
  }

  #[Test]
  public function testFailsWhenTheNameIsBlank(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute(['name' => '   ', 'owner' => self::OWNER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Organization name is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheOwnerIsBlank(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute(['name' => 'Acme Corp', 'owner' => '   ']);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Owner (user ID or email) is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheOwnerCannotBeResolved(): void
  {
    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findByEmail')->willReturn(null);

    $tester = new CommandTester($this->createCommand(
      $this->neverDispatchingBus(),
      $userRepository,
    ));

    $exitCode = $tester->execute(['name' => 'Acme Corp', 'owner' => self::OWNER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Failed to resolve owner', $tester->getDisplay());
  }

  #[Test]
  public function testReportsAFailureWhenTheCommandBusThrows(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('slug already taken'));

    $tester = new CommandTester($this->createCommand($commandBus));

    $exitCode = $tester->execute(['name' => 'Acme Corp', 'owner' => self::OWNER_EMAIL]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('slug already taken', $tester->getDisplay());
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
    ?UserRepositoryPort $userRepository = null,
  ): CreateOrganizationConsoleCommand {
    if (null === $userRepository) {
      $userRepository = $this->createStub(UserRepositoryPort::class);
      $userRepository->method('findById')->willReturn($this->user());
      $userRepository->method('findByEmail')->willReturn($this->user());
    }

    return new CreateOrganizationConsoleCommand(
      commandBus: $commandBus,
      userRepository: $userRepository,
    );
  }

  private function user(): User
  {
    return User::register(
      id: new UserId(self::OWNER_USER_ID),
      username: new Username('owner'),
      email: new Email(self::OWNER_EMAIL),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('Owner', 'User', null),
      eventIdProvider: new TestEventIdProvider(),
    );
  }

  private function successResult(): CreateOrganizationResult
  {
    return new CreateOrganizationResult(
      organizationId: self::ORGANIZATION_ID,
      ownerMemberId: '550e8400-e29b-41d4-a716-446655440011',
      ownerRoleId: '550e8400-e29b-41d4-a716-446655440012',
      name: 'Acme Corp',
      slug: 'acme-corp',
      ownerUserId: self::OWNER_USER_ID,
      createdByUserId: self::OWNER_USER_ID,
      status: 'active',
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
