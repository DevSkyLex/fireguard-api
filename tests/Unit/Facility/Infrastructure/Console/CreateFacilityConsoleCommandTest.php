<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Console;

use DateTimeImmutable;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{
  CreateFacilityCommand,
  CreateFacilityResult
};
use Facility\Domain\ValueObject\FacilityType;
use Facility\Infrastructure\Console\CreateFacilityConsoleCommand;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test CreateFacilityConsoleCommand.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateFacilityConsoleCommand::class)]
final class CreateFacilityConsoleCommandTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440020';

  private const string PARENT_FACILITY_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655440001';
  // #endregion

  // #region Methods
  #[Test]
  public function testConfigureDeclaresArgumentsAndOptions(): void
  {
    $definition = $this->createCommand(
      $this->createStub(CommandBusPort::class),
    )->getDefinition();

    self::assertTrue($definition->hasArgument('organization-id'));
    self::assertTrue($definition->hasArgument('name'));
    self::assertTrue($definition->hasArgument('type'));
    self::assertTrue($definition->hasOption('code'));
    self::assertTrue($definition->hasOption('address'));
    self::assertTrue($definition->hasOption('parent-facility-id'));
  }

  #[Test]
  public function testCreatesTheFacilityAndPrintsItsDetails(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof CreateFacilityCommand
        && self::ORGANIZATION_ID === $command->organizationId
        && 'Main Site' === $command->name
        && FacilityType::SITE->value === $command->type
        && 'BLDG-001' === $command->code
        && '10 rue de la Paix' === $command->address
        && self::PARENT_FACILITY_ID === $command->parentFacilityId))
      ->willReturn($this->successResult());

    $tester = new CommandTester($this->createCommand($commandBus));

    $exitCode = $tester->execute([
      'organization-id' => '  ' . self::ORGANIZATION_ID . '  ',
      'name' => '  Main Site  ',
      'type' => '  site  ',
      '--code' => '  BLDG-001  ',
      '--address' => '  10 rue de la Paix  ',
      '--parent-facility-id' => '  ' . self::PARENT_FACILITY_ID . '  ',
    ]);

    $display = $tester->getDisplay();

    self::assertSame(Command::SUCCESS, $exitCode);
    self::assertStringContainsString(self::FACILITY_ID, $display);
    self::assertStringContainsString('Main Site', $display);
  }

  #[Test]
  public function testBlankOptionsBecomeNull(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof CreateFacilityCommand
        && null === $command->code
        && null === $command->address
        && null === $command->parentFacilityId))
      ->willReturn($this->successResult());

    $tester = new CommandTester($this->createCommand($commandBus));

    $exitCode = $tester->execute([
      'organization-id' => self::ORGANIZATION_ID,
      'name' => 'Main Site',
      'type' => FacilityType::SITE->value,
      '--code' => '   ',
      '--address' => '   ',
      '--parent-facility-id' => '   ',
    ]);

    self::assertSame(Command::SUCCESS, $exitCode);
  }

  #[Test]
  public function testFailsWhenTheOrganizationIdIsBlank(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute([
      'organization-id' => '   ',
      'name' => 'Main Site',
      'type' => FacilityType::SITE->value,
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Organization ID is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheNameIsBlank(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute([
      'organization-id' => self::ORGANIZATION_ID,
      'name' => '   ',
      'type' => FacilityType::SITE->value,
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Facility name is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheTypeIsBlank(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute([
      'organization-id' => self::ORGANIZATION_ID,
      'name' => 'Main Site',
      'type' => '   ',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Facility type is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsOnAnUnknownFacilityType(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute([
      'organization-id' => self::ORGANIZATION_ID,
      'name' => 'Main Site',
      'type' => 'spaceship',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Invalid facility type', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheOrganizationIdIsNotAUuid(): void
  {
    $tester = new CommandTester($this->createCommand($this->neverDispatchingBus()));

    $exitCode = $tester->execute([
      'organization-id' => 'not-a-uuid',
      'name' => 'Main Site',
      'type' => FacilityType::SITE->value,
    ]);

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

    $exitCode = $tester->execute([
      'organization-id' => self::ORGANIZATION_ID,
      'name' => 'Main Site',
      'type' => FacilityType::SITE->value,
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('not found', $tester->getDisplay());
  }

  #[Test]
  public function testReportsAFailureWhenTheCommandBusThrows(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('duplicate code'));

    $tester = new CommandTester($this->createCommand($commandBus));

    $exitCode = $tester->execute([
      'organization-id' => self::ORGANIZATION_ID,
      'name' => 'Main Site',
      'type' => FacilityType::SITE->value,
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('duplicate code', $tester->getDisplay());
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
  ): CreateFacilityConsoleCommand {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn(
      false === $organization ? $this->organization() : $organization,
    );

    return new CreateFacilityConsoleCommand(
      commandBus: $commandBus,
      organizationRepository: $repository,
    );
  }

  private function organization(): Organization
  {
    return Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Test'),
      ownerUserId: self::OWNER_USER_ID,
    );
  }

  private function successResult(): CreateFacilityResult
  {
    return new CreateFacilityResult(
      facilityId: self::FACILITY_ID,
      organizationId: self::ORGANIZATION_ID,
      parentFacilityId: null,
      type: FacilityType::SITE->value,
      name: 'Main Site',
      code: null,
      status: 'active',
      address: null,
      metadata: [],
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
