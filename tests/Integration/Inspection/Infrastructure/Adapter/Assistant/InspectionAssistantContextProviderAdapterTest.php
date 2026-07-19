<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter\Assistant;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextScope};
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Adapter\Assistant\InspectionAssistantContextProviderAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Inspection\Infrastructure\Persistence\Doctrine\Repository\NonConformityRepository;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function mb_strpos;

/**
 * Test InspectionAssistantContextProviderAdapterTest.
 *
 * Executes the adapter's DQL query against a real database. The unit-level
 * counterpart (`tests/Unit/Inspection`) mocks the query bus/ports for the
 * permission-gate behaviour, but never parses this DQL — which is exactly
 * why this integration test exists (see `ChecklistRepositoryIntegrationTest`'s
 * docblock for the reserved-`member`-keyword incident this pattern guards
 * against).
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionAssistantContextProviderAdapter::class)]
final class InspectionAssistantContextProviderAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655481001';

  private const string OTHER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655481002';

  private const string INSPECTION_ID = '880e8400-e29b-41d4-a716-446655481101';

  private const string OTHER_INSPECTION_ID = '880e8400-e29b-41d4-a716-446655481102';

  private const string ACTOR_USER_ID = 'user-1';

  private EntityManagerInterface $entityManager;

  private InspectionAssistantContextProviderAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->adapter = new InspectionAssistantContextProviderAdapter(
      $authorization,
      new NonConformityRepository($this->entityManager),
      $this->entityManager,
    );

    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'Inspection Assistant Context Org', 'inspection-assistant-context-org');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Foreign Org', 'inspection-assistant-context-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    $inspection = $this->createInspection(self::INSPECTION_ID, $organization);
    $otherInspection = $this->createInspection(self::OTHER_INSPECTION_ID, $otherOrganization);
    $this->entityManager->persist($inspection);
    $this->entityManager->persist($otherInspection);

    $this->entityManager->persist($this->createNonConformity('nc-critical-open', $inspection, 'critical', 'open', 'Sprinkler head corroded beyond safe operation'));
    $this->entityManager->persist($this->createNonConformity('nc-high-in-progress', $inspection, 'high', 'in_progress', 'Fire door does not fully close'));
    $this->entityManager->persist($this->createNonConformity('nc-low-open', $inspection, 'low', 'open', 'Minor signage missing'));
    $this->entityManager->persist($this->createNonConformity('nc-done', $inspection, 'critical', 'done', 'Already resolved — must be excluded'));
    $this->entityManager->persist($this->createNonConformity('nc-foreign-org', $otherInspection, 'critical', 'open', 'Foreign organization — must never leak'));

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testProvideOrdersBySeverityAndExcludesResolvedAndForeignOrganizationRows(): void
  {
    $fragment = $this->adapter->provide(
      self::ORGANIZATION_ID,
      new AssistantContextScope(self::ACTOR_USER_ID, 'thread-1'),
      new AssistantContextBudget(4000),
    );

    self::assertFalse($fragment->isEmpty());
    self::assertStringContainsString('3 total', $fragment->text);
    self::assertStringContainsString('Sprinkler head corroded', $fragment->text);
    self::assertStringContainsString('Fire door does not fully close', $fragment->text);
    self::assertStringContainsString('Minor signage missing', $fragment->text);
    self::assertStringNotContainsString('Already resolved', $fragment->text);
    self::assertStringNotContainsString('Foreign organization', $fragment->text);

    // Most severe first: critical before high before low.
    $criticalPosition = mb_strpos($fragment->text, 'Sprinkler head corroded');
    $highPosition = mb_strpos($fragment->text, 'Fire door does not fully close');
    $lowPosition = mb_strpos($fragment->text, 'Minor signage missing');
    self::assertNotFalse($criticalPosition);
    self::assertNotFalse($highPosition);
    self::assertNotFalse($lowPosition);
    self::assertLessThan($highPosition, $criticalPosition);
    self::assertLessThan($lowPosition, $highPosition);
  }

  #[Test]
  public function testProvideReturnsAnEmptyFragmentWhenTheOrganizationHasNoOpenNonConformities(): void
  {
    $fragment = $this->adapter->provide(
      self::OTHER_ORGANIZATION_ID,
      new AssistantContextScope(self::ACTOR_USER_ID, 'thread-1'),
      new AssistantContextBudget(4000),
    );

    // The foreign-org non-conformity ('nc-foreign-org') DOES belong to
    // OTHER_ORGANIZATION_ID, so it must actually be reported here — this
    // pins that organization-scoping is symmetric, not merely "excluded from
    // the wrong organization's fragment".
    self::assertFalse($fragment->isEmpty());
    self::assertStringContainsString('Foreign organization', $fragment->text);
    self::assertStringNotContainsString('Sprinkler head corroded', $fragment->text);
  }

  private function createOrganization(string $id, string $name, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '880e8400-e29b-41d4-a716-446655489000';
    $organization->createdByUserId = '880e8400-e29b-41d4-a716-446655489000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function createInspection(string $id, OrganizationRecord $organization): InspectionRecord
  {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = '880e8400-e29b-41d4-a716-446655488888';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Jane Doe';
    $inspection->result = 'fail';
    $inspection->status = 'submitted';
    $inspection->performedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;

    return $inspection;
  }

  private function createNonConformity(string $id, InspectionRecord $inspection, string $severity, string $status, string $description): NonConformityRecord
  {
    $nonConformity = new NonConformityRecord();
    $nonConformity->id = $id;
    $nonConformity->inspection = $inspection;
    $nonConformity->description = $description;
    $nonConformity->severity = $severity;
    $nonConformity->status = $status;
    $nonConformity->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $nonConformity->updatedAt = $nonConformity->createdAt;

    return $nonConformity;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement('DELETE FROM non_conformities WHERE inspection_id IN (:inspectionIds)', [
      'inspectionIds' => [self::INSPECTION_ID, self::OTHER_INSPECTION_ID],
    ], ['inspectionIds' => ArrayParameterType::STRING]);
    $connection->executeStatement('DELETE FROM inspections WHERE id IN (:inspectionIds)', [
      'inspectionIds' => [self::INSPECTION_ID, self::OTHER_INSPECTION_ID],
    ], ['inspectionIds' => ArrayParameterType::STRING]);
    $connection->executeStatement('DELETE FROM organizations WHERE id IN (:organizationIds)', [
      'organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID],
    ], ['organizationIds' => ArrayParameterType::STRING]);
    $this->entityManager->clear();
  }
}
