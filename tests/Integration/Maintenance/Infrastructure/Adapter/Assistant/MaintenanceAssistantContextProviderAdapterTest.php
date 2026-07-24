<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\Adapter\Assistant;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextScope};
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleSnapshot;
use Maintenance\Infrastructure\Adapter\Assistant\MaintenanceAssistantContextProviderAdapter;
use Maintenance\Infrastructure\Persistence\Doctrine\Repository\MaintenanceScheduleRepository;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function strpos;

/**
 * Test MaintenanceAssistantContextProviderAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceAssistantContextProviderAdapter::class)]
final class MaintenanceAssistantContextProviderAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'aa0e8400-e29b-41d4-a716-4466554a0001';

  private const string ACTOR_USER_ID = 'aa0e8400-e29b-41d4-a716-4466554a9000';

  private const string EQUIPMENT_ID_A = 'aa0e8400-e29b-41d4-a716-4466554a0010';

  private const string EQUIPMENT_ID_B = 'aa0e8400-e29b-41d4-a716-4466554a0011';

  private const string EQUIPMENT_ID_C = 'aa0e8400-e29b-41d4-a716-4466554a0012';

  private EntityManagerInterface $entityManager;

  private MaintenanceScheduleRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    /** @var UuidFactory $uuidFactory */
    $uuidFactory = static::getContainer()->get(UuidFactory::class);

    $this->cleanup();

    $this->repository = new MaintenanceScheduleRepository($this->entityManager, $uuidFactory);

    $this->createOrganization(self::ORGANIZATION_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSupportsDelegatesToAuthorizationPermission(): void
  {
    $granting = $this->adapter($this->authorization(true));
    $denying = $this->adapter($this->authorization(false));

    $scope = new AssistantContextScope(self::ACTOR_USER_ID, 'thread-1');

    self::assertTrue($granting->supports(self::ORGANIZATION_ID, $scope));
    self::assertFalse($denying->supports(self::ORGANIZATION_ID, $scope));
  }

  #[Test]
  public function testProvideReturnsEmptyFragmentWhenNoDueSchedules(): void
  {
    // Only an up_to_date schedule exists: neither overdue nor due_soon.
    $this->repository->save($this->snapshot(self::EQUIPMENT_ID_A, 'fire_extinguisher', 'up_to_date', new DateTimeImmutable('2026-12-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $fragment = $this->adapter($this->authorization(true))->provide(
      self::ORGANIZATION_ID,
      new AssistantContextScope(self::ACTOR_USER_ID, 'thread-1'),
      new AssistantContextBudget(4000),
    );

    self::assertSame('maintenance.upcoming_due_dates', $fragment->sourceKey);
    self::assertTrue($fragment->isEmpty());
  }

  #[Test]
  public function testProvideRendersCountsAndOrdersBySoonestDueFirst(): void
  {
    // overdue due 2026-08-01, due_soon due 2026-09-01, up_to_date ignored.
    $this->repository->save($this->snapshot(self::EQUIPMENT_ID_A, 'fire_extinguisher', 'overdue', new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    $this->repository->save($this->snapshot(self::EQUIPMENT_ID_B, 'fire_hose', 'due_soon', new DateTimeImmutable('2026-09-01T00:00:00+00:00')));
    $this->repository->save($this->snapshot(self::EQUIPMENT_ID_C, 'sprinkler', 'up_to_date', new DateTimeImmutable('2026-10-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $fragment = $this->adapter($this->authorization(true))->provide(
      self::ORGANIZATION_ID,
      new AssistantContextScope(self::ACTOR_USER_ID, 'thread-1'),
      new AssistantContextBudget(4000),
    );

    self::assertFalse($fragment->isEmpty());
    self::assertStringContainsString('1 overdue, 1 due soon', $fragment->text);
    self::assertStringContainsString('- fire_extinguisher equipment (overdue, due 2026-08-01)', $fragment->text);
    self::assertStringContainsString('- fire_hose equipment (due_soon, due 2026-09-01)', $fragment->text);
    self::assertStringNotContainsString('sprinkler', $fragment->text);

    // Soonest due first: the overdue (08-01) line precedes the due_soon (09-01) line.
    $overduePosition = strpos($fragment->text, 'fire_extinguisher equipment');
    $dueSoonPosition = strpos($fragment->text, 'fire_hose equipment');
    self::assertNotFalse($overduePosition);
    self::assertNotFalse($dueSoonPosition);
    self::assertLessThan($dueSoonPosition, $overduePosition);
  }

  private function adapter(OrganizationAuthorizationPort $authorization): MaintenanceAssistantContextProviderAdapter
  {
    return new MaintenanceAssistantContextProviderAdapter($authorization, $this->repository);
  }

  private function authorization(bool $granted): OrganizationAuthorizationPort
  {
    $authorization = self::createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($granted);

    return $authorization;
  }

  private function snapshot(
    string $equipmentId,
    string $equipmentType,
    string $dueStatus,
    ?DateTimeImmutable $nextDueAt,
  ): MaintenanceScheduleSnapshot {
    return new MaintenanceScheduleSnapshot(
      null,
      self::ORGANIZATION_ID,
      $equipmentId,
      null,
      $equipmentType,
      null,
      null,
      $nextDueAt,
      $dueStatus,
    );
  }

  private function createOrganization(string $id): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Maintenance Assistant Context Provider Adapter Test ' . $id;
    $organization->slug = 'maintenance-assistant-context-provider-adapter-test-' . $id;
    $organization->ownerUserId = self::ACTOR_USER_ID;
    $organization->createdByUserId = self::ACTOR_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM maintenance_schedules WHERE organization_id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
