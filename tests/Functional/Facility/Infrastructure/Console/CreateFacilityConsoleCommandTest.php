<?php

declare(strict_types=1);

namespace Tests\Functional\Facility\Infrastructure\Console;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Console\CreateFacilityConsoleCommand;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function chr;
use function ord;
use function random_bytes;
use function sprintf;
use function substr;
use function uniqid;

/**
 * Test CreateFacilityConsoleCommandTest.
 *
 * @category Functional Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateFacilityConsoleCommand::class)]
final class CreateFacilityConsoleCommandTest extends KernelTestCase
{
  private const string COMMAND_NAME = 'app:facility:create';

  // #region Properties
  /**
   * Property application.
   *
   * The console application built from the kernel.
   */
  private ?Application $application = null;

  /**
   * Property commandTester.
   *
   * The tester wrapping the create-facility command.
   */
  private ?CommandTester $commandTester = null;

  /**
   * Property entityManager.
   *
   * The main-database entity manager owning organizations and facilities.
   */
  private ?EntityManagerInterface $entityManager = null;

  /**
   * Property organizationId.
   *
   * The unique organization identifier seeded and cleaned up per test.
   */
  private string $organizationId = '';
  // #endregion

  // #region Methods
  /**
   * Method setUp.
   *
   * Boots the kernel, builds the console application, resolves the command and
   * wraps it in a CommandTester.
   *
   * @return void No return value
   */
  protected function setUp(): void
  {
    $kernel = self::bootKernel();
    $this->application = new Application(kernel: $kernel);

    $command = $this->application->find(name: self::COMMAND_NAME);
    $this->commandTester = new CommandTester(command: $command);

    /** @var EntityManagerInterface $entityManager */
    $entityManager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->organizationId = $this->generateUuid();
  }

  /**
   * Method tearDown.
   *
   * Removes the rows created during the test in a foreign-key-safe order
   * (facilities before organizations) and closes the entity manager.
   *
   * @return void No return value
   */
  protected function tearDown(): void
  {
    if (null !== $this->entityManager && $this->entityManager->isOpen()) {
      $connection = $this->entityManager->getConnection();
      $connection->executeStatement(
        'DELETE FROM facilities WHERE organization_id = ?',
        [$this->organizationId],
      );
      $connection->executeStatement(
        'DELETE FROM organizations WHERE id = ?',
        [$this->organizationId],
      );

      $this->entityManager->close();
    }

    parent::tearDown();
  }

  /**
   * Method testCreatesFacilitySuccessfully.
   *
   * Happy path: with an existing organization the command dispatches the use
   * case, reports success and persists a facility row.
   *
   * @return void No return value
   */
  #[Test]
  public function testCreatesFacilitySuccessfully(): void
  {
    $this->persistOrganization();

    $this->commandTester?->execute([
      'organization-id' => $this->organizationId,
      'name' => 'Main Site',
      'type' => 'site',
      '--code' => 'CLI-' . uniqid(),
      '--address' => '12 Rue des Pompiers, Paris',
    ]);

    $this->commandTester?->assertCommandIsSuccessful();

    $display = (string) $this->commandTester?->getDisplay();
    self::assertStringContainsString('created successfully', $display);
    self::assertStringContainsString('Main Site', $display);
    self::assertStringContainsString($this->organizationId, $display);

    $count = $this->entityManager?->getConnection()->fetchOne(
      'SELECT COUNT(*) FROM facilities WHERE organization_id = ?',
      [$this->organizationId],
    );
    /** @var int|string $count */
    self::assertGreaterThanOrEqual(1, (int) $count);
  }

  /**
   * Method testFailsWhenOrganizationIdIsEmpty.
   *
   * Guard branch: a blank organization identifier is rejected before any lookup.
   *
   * @return void No return value
   */
  #[Test]
  public function testFailsWhenOrganizationIdIsEmpty(): void
  {
    $this->commandTester?->execute([
      'organization-id' => '   ',
      'name' => 'Main Site',
      'type' => 'site',
    ]);

    self::assertSame(Command::FAILURE, $this->commandTester?->getStatusCode());
    self::assertStringContainsString('Organization ID is required.', $this->commandTester->getDisplay());
  }

  /**
   * Method testFailsWithInvalidType.
   *
   * Guard branch: an unsupported facility type is rejected with the list of
   * valid types.
   *
   * @return void No return value
   */
  #[Test]
  public function testFailsWithInvalidType(): void
  {
    $this->commandTester?->execute([
      'organization-id' => $this->organizationId,
      'name' => 'Main Site',
      'type' => 'skyscraper',
    ]);

    self::assertSame(Command::FAILURE, $this->commandTester?->getStatusCode());
    self::assertStringContainsString('Invalid facility type', $this->commandTester->getDisplay());
  }

  /**
   * Method testFailsWithMalformedOrganizationId.
   *
   * Guard branch: a non-UUID organization identifier surfaces the value-object
   * validation error.
   *
   * @return void No return value
   */
  #[Test]
  public function testFailsWithMalformedOrganizationId(): void
  {
    $this->commandTester?->execute([
      'organization-id' => 'not-a-uuid',
      'name' => 'Main Site',
      'type' => 'site',
    ]);

    self::assertSame(Command::FAILURE, $this->commandTester?->getStatusCode());
    self::assertStringContainsString('Invalid organization ID', $this->commandTester->getDisplay());
  }

  /**
   * Method testFailsWhenOrganizationNotFound.
   *
   * Guard branch: a well-formed but unknown organization identifier reports a
   * not-found error.
   *
   * @return void No return value
   */
  #[Test]
  public function testFailsWhenOrganizationNotFound(): void
  {
    $this->commandTester?->execute([
      'organization-id' => $this->organizationId,
      'name' => 'Main Site',
      'type' => 'site',
    ]);

    self::assertSame(Command::FAILURE, $this->commandTester?->getStatusCode());
    self::assertStringContainsString('not found', $this->commandTester->getDisplay());
  }

  /**
   * Method testFailsWhenParentFacilityDoesNotExist.
   *
   * Failure branch: when the use case throws, the command catches it and reports
   * a creation failure rather than a success.
   *
   * @return void No return value
   */
  #[Test]
  public function testFailsWhenParentFacilityDoesNotExist(): void
  {
    $this->persistOrganization();

    $this->commandTester?->execute([
      'organization-id' => $this->organizationId,
      'name' => 'Floor 1',
      'type' => 'floor',
      '--parent-facility-id' => $this->generateUuid(),
    ]);

    self::assertSame(Command::FAILURE, $this->commandTester?->getStatusCode());
    self::assertStringContainsString('Failed to create facility', $this->commandTester->getDisplay());
  }

  /**
   * Method testRequiresMandatoryArguments.
   *
   * The console definition enforces the three required arguments.
   *
   * @return void No return value
   */
  #[Test]
  public function testRequiresMandatoryArguments(): void
  {
    $this->expectException(exception: RuntimeException::class);
    $this->expectExceptionMessage(message: 'Not enough arguments');

    $this->commandTester?->execute(input: []);
  }

  /**
   * Method persistOrganization.
   *
   * Persists a minimal active organization with no plan so the quota check is a
   * no-op, keeping the happy path independent of plan seeding.
   *
   * @return void No return value
   */
  private function persistOrganization(): void
  {
    $now = new DateTimeImmutable();

    $organization = new OrganizationRecord();
    $organization->id = $this->organizationId;
    $organization->name = 'CLI Test Organization';
    $organization->slug = 'cli-test-org-' . uniqid();
    $organization->ownerUserId = $this->generateUuid();
    $organization->createdByUserId = $this->generateUuid();
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->planId = null;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;

    $this->entityManager?->persist($organization);
    $this->entityManager?->flush();
  }

  /**
   * Method generateUuid.
   *
   * Builds a fresh RFC 4122 version 4 UUID so parallel runs never collide.
   *
   * @return string the generated UUID
   */
  private function generateUuid(): string
  {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
      '%s-%s-%s-%s-%s',
      substr($hex, 0, 8),
      substr($hex, 8, 4),
      substr($hex, 12, 4),
      substr($hex, 16, 4),
      substr($hex, 20, 12),
    );
  }
  // #endregion
}
