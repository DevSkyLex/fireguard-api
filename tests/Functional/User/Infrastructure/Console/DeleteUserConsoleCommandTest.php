<?php

declare(strict_types=1);

namespace Tests\Functional\User\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use User\Application\UseCase\Command\User\CreateUser\CreateUserCommand;
use User\Infrastructure\Console\DeleteUserConsoleCommand;

use function preg_replace;
use function uniqid;

/**
 * Test DeleteUserConsoleCommandTest.
 *
 * Functional coverage for the `app:user:delete` console command driven through
 * a real kernel, container and CommandTester. Users are seeded via the command
 * bus on the auth entity manager; DAMA wraps each test in a transaction that is
 * rolled back on teardown, and a best-effort raw DELETE guards against leftovers.
 *
 * @category Functional Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeleteUserConsoleCommand::class)]
final class DeleteUserConsoleCommandTest extends KernelTestCase
{
  // #region Properties
  /**
   * Property application.
   *
   * The console application built from the kernel.
   */
  private ?Application $application = null;

  /**
   * Property createdUserEmails.
   *
   * Emails of users seeded during a test, cleaned up on teardown.
   *
   * @var array<int, string>
   */
  private array $createdUserEmails = [];
  // #endregion

  // #region Lifecycle
  /**
   * Method setUp.
   *
   * Boots the kernel and wires the console application.
   *
   * @return void No return value
   */
  protected function setUp(): void
  {
    $kernel = self::bootKernel();
    $this->application = new Application(kernel: $kernel);
  }

  /**
   * Method tearDown.
   *
   * Removes any seeded users (raw DELETE on the auth connection) so runs never
   * collide, then shuts the kernel down.
   *
   * @return void No return value
   */
  protected function tearDown(): void
  {
    foreach ($this->createdUserEmails as $email) {
      try {
        $this->authConnection()->executeStatement(
          'DELETE FROM users WHERE email = ?',
          [$email],
        );
      } catch (Throwable) {
        // Best-effort: DAMA already rolls back the surrounding transaction.
      }
    }

    $this->createdUserEmails = [];
    $this->application = null;

    parent::tearDown();
  }
  // #endregion

  // #region Tests
  /**
   * Method testDeletesAnExistingUserByEmail.
   *
   * Happy path: an existing user is removed when addressed by email with
   * `--force` to skip the confirmation prompt.
   *
   * @return void No return value
   */
  #[Test]
  public function testDeletesAnExistingUserByEmail(): void
  {
    $email = $this->seedUser();
    self::assertTrue($this->userExists($email), 'Fixture user should exist before deletion.');

    $tester = $this->commandTester();
    $tester->execute([
      'identifier' => $email,
      '--force' => true,
    ]);

    $tester->assertCommandIsSuccessful();
    self::assertStringContainsString('successfully', $this->normalize($tester->getDisplay()));
    self::assertFalse($this->userExists($email), 'User should be gone after deletion.');
  }

  /**
   * Method testCancelsDeletionWhenNotConfirmed.
   *
   * Interactive guard: answering "no" to the confirmation leaves the user in
   * place and still exits successfully.
   *
   * @return void No return value
   */
  #[Test]
  public function testCancelsDeletionWhenNotConfirmed(): void
  {
    $email = $this->seedUser();

    $tester = $this->commandTester();
    $tester->setInputs(['no']);
    $tester->execute(['identifier' => $email]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsStringIgnoringCase('cancelled', $this->normalize($tester->getDisplay()));
    self::assertTrue($this->userExists($email), 'User must survive a cancelled deletion.');
  }

  /**
   * Method testFailsWhenEmailNotFound.
   *
   * Failure branch: an unknown email resolves to no user and the command fails.
   *
   * @return void No return value
   */
  #[Test]
  public function testFailsWhenEmailNotFound(): void
  {
    $email = 'missing-' . uniqid() . '@example.com';

    $tester = $this->commandTester();
    $tester->execute([
      'identifier' => $email,
      '--force' => true,
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('not found', $this->normalize($tester->getDisplay()));
  }

  /**
   * Method testFailsWhenUserIdNotFound.
   *
   * Failure branch: a well-formed but unknown UUID exercises the ID lookup path.
   *
   * @return void No return value
   */
  #[Test]
  public function testFailsWhenUserIdNotFound(): void
  {
    $tester = $this->commandTester();
    $tester->execute([
      'identifier' => '01920000-0000-7000-8000-000000000000',
      '--force' => true,
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('not found', $this->normalize($tester->getDisplay()));
  }

  /**
   * Method testFailsWhenIdentifierIsEmpty.
   *
   * Guard branch: a blank identifier is rejected before any lookup.
   *
   * @return void No return value
   */
  #[Test]
  public function testFailsWhenIdentifierIsEmpty(): void
  {
    $tester = $this->commandTester();
    $tester->execute(['identifier' => '']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Identifier is required', $this->normalize($tester->getDisplay()));
  }
  // #endregion

  // #region Helpers
  /**
   * Method commandTester.
   *
   * Resolves the delete command and wraps it in a CommandTester.
   *
   * @return CommandTester the tester
   */
  private function commandTester(): CommandTester
  {
    $command = $this->application?->find(name: 'app:user:delete');
    self::assertNotNull($command);

    return new CommandTester(command: $command);
  }

  /**
   * Method seedUser.
   *
   * Creates a user through the command bus and returns its unique email.
   *
   * @return string the seeded user email
   */
  private function seedUser(): string
  {
    $suffix = uniqid();
    $email = 'del-' . $suffix . '@example.com';

    /** @var MessageBusInterface $bus */
    $bus = self::getContainer()->get(MessageBusInterface::class);
    $bus->dispatch(new CreateUserCommand(
      username: 'deltest' . $suffix,
      email: $email,
      password: 'TestPassword123!',
      firstName: 'Del',
      lastName: 'Test',
    ));

    $this->createdUserEmails[] = $email;

    return $email;
  }

  /**
   * Method userExists.
   *
   * Checks the auth `users` table for a row with the given email.
   *
   * @param string $email the email to look up
   *
   * @return bool true when a matching row exists
   */
  private function userExists(string $email): bool
  {
    $count = $this->authConnection()->fetchOne(
      'SELECT COUNT(*) FROM users WHERE email = ?',
      [$email],
    );

    /** @var int|string $count */
    return (int) $count > 0;
  }

  /**
   * Method authConnection.
   *
   * Returns the DBAL connection backing the auth entity manager.
   *
   * @return Connection the auth connection
   */
  private function authConnection(): Connection
  {
    /** @var Connection $connection */
    $connection = self::getContainer()->get('doctrine.dbal.auth_connection');

    return $connection;
  }

  /**
   * Method normalize.
   *
   * Collapses console whitespace so wrapped block messages assert reliably.
   *
   * @param string $display the raw command display
   *
   * @return string the whitespace-normalized display
   */
  private function normalize(string $display): string
  {
    return (string) preg_replace('/\s+/', ' ', $display);
  }
  // #endregion
}
