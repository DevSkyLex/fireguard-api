<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Facility;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Facility\FacilityInterventionDependencyAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityInterventionDependencyAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityInterventionDependencyAdapter::class)]
final class FacilityInterventionDependencyAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'f20e8400-e29b-41d4-a716-446655f20001';

  private const string OTHER_ORGANIZATION_ID = 'f20e8400-e29b-41d4-a716-446655f20002';

  private const string FACILITY_ACTIVE = 'f20e8400-e29b-41d4-a716-446655f2a001';

  private const string FACILITY_CLOSED = 'f20e8400-e29b-41d4-a716-446655f2c001';

  private const string FACILITY_DRAFT = 'f20e8400-e29b-41d4-a716-446655f2d001';

  private const string FACILITY_FOREIGN = 'f20e8400-e29b-41d4-a716-446655f2e001';

  private const string INTERVENTION_ACTIVE = 'f20e8400-e29b-41d4-a716-446655f21001';

  private const string INTERVENTION_PUBLISHED = 'f20e8400-e29b-41d4-a716-446655f21002';

  private const string INTERVENTION_ABANDONED = 'f20e8400-e29b-41d4-a716-446655f21003';

  private const string INTERVENTION_DRAFT = 'f20e8400-e29b-41d4-a716-446655f21004';

  private const string INTERVENTION_FOREIGN = 'f20e8400-e29b-41d4-a716-446655f21005';

  private const string RESPONSIBLE_ID = 'f20e8400-e29b-41d4-a716-446655f29000';

  private const string OWNER_USER_ID = 'f20e8400-e29b-41d4-a716-446655f29001';

  private EntityManagerInterface $entityManager;

  private FacilityInterventionDependencyAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new FacilityInterventionDependencyAdapter($this->entityManager);

    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'Intervention Dependency Org', 'intervention-dependency-org');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Intervention Dependency Org B', 'intervention-dependency-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    // In progress: an active dependency.
    $this->entityManager->persist($this->createIntervention(
      id: self::INTERVENTION_ACTIVE,
      number: 1,
      organization: $organization,
      siteId: self::FACILITY_ACTIVE,
      status: 'in_progress',
    ));
    // Published: closed, no longer an active dependency.
    $this->entityManager->persist($this->createIntervention(
      id: self::INTERVENTION_PUBLISHED,
      number: 2,
      organization: $organization,
      siteId: self::FACILITY_CLOSED,
      status: 'published',
    ));
    // Abandoned: closed, no longer an active dependency.
    $this->entityManager->persist($this->createIntervention(
      id: self::INTERVENTION_ABANDONED,
      number: 3,
      organization: $organization,
      siteId: self::FACILITY_CLOSED,
      status: 'abandoned',
    ));
    // Draft: still mutable (not a closed status), so still an active dependency.
    $this->entityManager->persist($this->createIntervention(
      id: self::INTERVENTION_DRAFT,
      number: 4,
      organization: $organization,
      siteId: self::FACILITY_DRAFT,
      status: 'draft',
    ));
    // Active intervention, but belonging to a different organization.
    $this->entityManager->persist($this->createIntervention(
      id: self::INTERVENTION_FOREIGN,
      number: 1,
      organization: $otherOrganization,
      siteId: self::FACILITY_FOREIGN,
      status: 'planned',
    ));

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testReturnsTrueWhenFacilityHasActiveIntervention(): void
  {
    self::assertTrue(
      $this->adapter->hasActiveInterventionInFacility(self::ORGANIZATION_ID, self::FACILITY_ACTIVE),
    );
  }

  #[Test]
  public function testReturnsFalseWhenOnlyClosedInterventions(): void
  {
    self::assertFalse(
      $this->adapter->hasActiveInterventionInFacility(self::ORGANIZATION_ID, self::FACILITY_CLOSED),
    );
  }

  #[Test]
  public function testTreatsDraftInterventionAsActive(): void
  {
    self::assertTrue(
      $this->adapter->hasActiveInterventionInFacility(self::ORGANIZATION_ID, self::FACILITY_DRAFT),
    );
  }

  #[Test]
  public function testIsScopedToOrganization(): void
  {
    // The active intervention in FACILITY_FOREIGN belongs to the other organization.
    self::assertFalse(
      $this->adapter->hasActiveInterventionInFacility(self::ORGANIZATION_ID, self::FACILITY_FOREIGN),
    );
    self::assertTrue(
      $this->adapter->hasActiveInterventionInFacility(self::OTHER_ORGANIZATION_ID, self::FACILITY_FOREIGN),
    );
  }

  private function createIntervention(
    string $id,
    int $number,
    OrganizationRecord $organization,
    string $siteId,
    string $status,
  ): InterventionRecord {
    $intervention = new InterventionRecord();
    $intervention->id = $id;
    $intervention->organization = $organization;
    $intervention->name = 'Intervention ' . $id;
    $intervention->number = $number;
    $intervention->status = $status;
    $intervention->siteId = $siteId;
    $intervention->responsibleId = self::RESPONSIBLE_ID;
    $intervention->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $intervention->updatedAt = $intervention->createdAt;

    return $intervention;
  }

  private function createOrganization(string $id, string $name, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $interventionIds = [
      self::INTERVENTION_ACTIVE,
      self::INTERVENTION_PUBLISHED,
      self::INTERVENTION_ABANDONED,
      self::INTERVENTION_DRAFT,
      self::INTERVENTION_FOREIGN,
    ];
    $connection->executeStatement(
      'DELETE FROM interventions WHERE id IN (:interventionIds)',
      ['interventionIds' => $interventionIds],
      ['interventionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
