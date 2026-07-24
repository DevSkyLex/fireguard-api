<?php

declare(strict_types=1);

namespace Tests\Functional\Organization\Infrastructure\Console;

use Organization\Infrastructure\Console\CreateOrganizationConsoleCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use User\Application\UseCase\Command\User\CreateUser\{CreateUserCommand, CreateUserResult};

use function uniqid;

/**
 * Test CreateOrganizationConsoleCommandTest.
 *
 * @category Functional Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateOrganizationConsoleCommand::class)]
final class CreateOrganizationConsoleCommandTest extends KernelTestCase
{
  // #region Constants
  /**
   * Constant COMMAND_NAME.
   *
   * The command name under test.
   */
  private const string COMMAND_NAME = 'app:organization:create';
  // #endregion

  // #region Properties
  /**
   * Property commandTester.
   *
   * The tester wrapping the command under test.
   */
  private CommandTester $commandTester;
  // #endregion

  // #region Methods
  /**
   * Method setUp.
   *
   * Boots the kernel, builds the console application and wraps the
   * command under test in a CommandTester.
   */
  protected function setUp(): void
  {
    $kernel = self::bootKernel();

    $application = new Application(kernel: $kernel);
    $command = $application->find(name: self::COMMAND_NAME);

    $this->commandTester = new CommandTester(command: $command);
  }

  /**
   * Method testCreatesOrganizationWithResolvedOwner.
   *
   * Happy path: a persisted owner is resolved by ID and the
   * organization is created successfully.
   */
  #[Test]
  public function testCreatesOrganizationWithResolvedOwner(): void
  {
    $ownerUserId = $this->createOwnerUser();
    $slug = 'acme-' . uniqid();

    $this->commandTester->execute([
      'name' => 'Acme ' . uniqid(),
      'owner' => $ownerUserId,
      '--slug' => $slug,
    ]);

    $this->commandTester->assertCommandIsSuccessful();

    $display = $this->commandTester->getDisplay();
    self::assertStringContainsString('successfully', $display);
    self::assertStringContainsString($slug, $display);
    self::assertStringContainsString($ownerUserId, $display);
  }

  /**
   * Method testFailsWhenNameIsBlank.
   *
   * Guard branch: a blank name is rejected before any owner lookup.
   */
  #[Test]
  public function testFailsWhenNameIsBlank(): void
  {
    $exitCode = $this->commandTester->execute([
      'name' => '   ',
      'owner' => 'someone@example.com',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Organization name is required.', $this->commandTester->getDisplay());
  }

  /**
   * Method testFailsWhenOwnerIsBlank.
   *
   * Guard branch: a blank owner is rejected.
   */
  #[Test]
  public function testFailsWhenOwnerIsBlank(): void
  {
    $exitCode = $this->commandTester->execute([
      'name' => 'Acme Corp',
      'owner' => '   ',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Owner (user ID or email) is required.', $this->commandTester->getDisplay());
  }

  /**
   * Method testFailsWhenOwnerIdIsUnknown.
   *
   * Failure branch: a well-formed but unknown owner UUID cannot be
   * resolved, so the command fails.
   */
  #[Test]
  public function testFailsWhenOwnerIdIsUnknown(): void
  {
    $exitCode = $this->commandTester->execute([
      'name' => 'Acme Corp',
      'owner' => '0190aaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Failed to resolve owner', $this->commandTester->getDisplay());
  }

  /**
   * Method testFailsWhenOwnerEmailIsUnknown.
   *
   * Failure branch: an unknown owner email cannot be resolved, so the
   * command fails.
   */
  #[Test]
  public function testFailsWhenOwnerEmailIsUnknown(): void
  {
    $exitCode = $this->commandTester->execute([
      'name' => 'Acme Corp',
      'owner' => 'ghost-' . uniqid() . '@example.com',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Failed to resolve owner', $this->commandTester->getDisplay());
  }

  /**
   * Method createOwnerUser.
   *
   * Persists a fresh user through the command bus and returns its ID,
   * to be used as the organization owner.
   *
   * @return string the created user ID
   */
  private function createOwnerUser(): string
  {
    /** @var CommandBusPort $commandBus */
    $commandBus = self::getContainer()->get(CommandBusPort::class);

    $suffix = uniqid();

    /** @var CreateUserResult $result */
    $result = $commandBus->dispatch(new CreateUserCommand(
      username: 'org-owner-' . $suffix,
      email: 'org-owner-' . $suffix . '@example.com',
      password: 'OwnerPassword123!',
      firstName: 'Org',
      lastName: 'Owner',
    ));

    return $result->userId;
  }
  // #endregion
}
