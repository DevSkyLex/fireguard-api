<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Adapter\Intervention\InterventionInspectionResultAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Intervention\Application\Contract\Result\InterventionInspectionResult;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Recorded inspection proof and publication scope on PostgreSQL.
 *
 * @category Adapter Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionInspectionResultAdapter::class)]
final class InterventionInspectionResultAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION = '7a3e8400-e29b-41d4-a716-4466557a3001';

  private const string OTHER_ORGANIZATION = '7a3e8400-e29b-41d4-a716-4466557a3002';

  private const string INTERVENTION = '7a3e8400-e29b-41d4-a716-4466557a3003';

  private const string OTHER_INTERVENTION = '7a3e8400-e29b-41d4-a716-4466557a3004';

  private const string INSPECTION = '7a3e8400-e29b-41d4-a716-4466557a3005';

  private const string EQUIPMENT = '7a3e8400-e29b-41d4-a716-4466557a3006';

  private const string AUTHOR = '7a3e8400-e29b-41d4-a716-4466557a3007';

  private const string UNKNOWN = '7a3e8400-e29b-41d4-a716-4466557a3999';

  private const string PERFORMED_AT = '2026-08-03T14:25:36+00:00';

  private EntityManagerInterface $entityManager;

  private InterventionInspectionResultAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $this->entityManager = $manager;
    $this->adapter = new InterventionInspectionResultAdapter($manager);
    $this->organization(self::ORGANIZATION, 'inspection-result-proof');
    $this->organization(self::OTHER_ORGANIZATION, 'foreign-inspection-result-proof');
    $manager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    if ($this->entityManager->isOpen()) {
      $this->entityManager->close();
    }
  }

  #[Test]
  #[DataProvider('recordedFacts')]
  public function matchingScopePreservesTheRecordedFact(string $result, string $status, string $recordStatus, ?string $authorId, ?string $notes): void
  {
    $inspection = $this->inspection(self::ORGANIZATION, self::INTERVENTION);
    $inspection->result = $result;
    $inspection->status = $status;
    $inspection->recordStatus = $recordStatus;
    $inspection->inspectorType = null === $authorId ? 'external' : 'user';
    $inspection->inspectorUserId = $authorId;
    $inspection->notes = $notes;
    $this->entityManager->flush();
    $this->entityManager->clear();

    $proof = $this->adapter->find(self::ORGANIZATION, self::INTERVENTION, self::INSPECTION);

    self::assertInstanceOf(InterventionInspectionResult::class, $proof);
    self::assertSame(self::INSPECTION, $proof->id);
    self::assertSame(self::EQUIPMENT, $proof->equipmentId);
    self::assertSame($result, $proof->result);
    self::assertSame($status, $proof->status);
    self::assertSame(self::PERFORMED_AT, $proof->performedAt->format('c'));
    self::assertSame($authorId, $proof->authorId);
    self::assertSame($notes, $proof->notes);
  }

  #[Test]
  public function absentInspectionReturnsNoProof(): void
  {
    self::assertNull($this->adapter->find(self::ORGANIZATION, self::INTERVENTION, self::UNKNOWN));
  }

  #[Test]
  #[DataProvider('unrelatedScopes')]
  public function existingInspectionOutsideThePublicationScopeReturnsNoProof(string $organizationId, ?string $interventionId): void
  {
    $this->inspection($organizationId, $interventionId);
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertNull($this->adapter->find(self::ORGANIZATION, self::INTERVENTION, self::INSPECTION));
  }

  #[Test]
  public function requestedForeignOrganizationCannotReadAnOtherwiseMatchingInspection(): void
  {
    $this->inspection(self::ORGANIZATION, self::INTERVENTION);
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertNull($this->adapter->find(self::OTHER_ORGANIZATION, self::INTERVENTION, self::INSPECTION));
  }

  /**
   * @return iterable<string,array{string,string,string,?string,?string}>
   */
  public static function recordedFacts(): iterable
  {
    yield 'published completed pass' => ['pass', 'closed', 'published', self::AUTHOR, "Vérification effectuée.\nAucune anomalie constatée."];
    yield 'unpublished completed fail' => ['fail', 'closed', 'draft', self::AUTHOR, 'Équipement à remplacer.'];
    yield 'submitted partial' => ['partial', 'submitted', 'draft', self::AUTHOR, 'Accès partiel au matériel.'];
    yield 'draft remains draft' => ['fail', 'draft', 'draft', null, null];
    yield 'cancelled remains cancelled' => ['partial', 'cancelled', 'published', null, 'Contrôle interrompu.'];
  }

  /**
   * @return iterable<string,array{string,?string}>
   */
  public static function unrelatedScopes(): iterable
  {
    yield 'foreign organization with matching intervention' => [self::OTHER_ORGANIZATION, self::INTERVENTION];
    yield 'sibling intervention with matching organization' => [self::ORGANIZATION, self::OTHER_INTERVENTION];
    yield 'standalone inspection with matching organization' => [self::ORGANIZATION, null];
  }

  private function organization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Inspection result proof';
    $organization->slug = $slug;
    $organization->ownerUserId = self::AUTHOR;
    $organization->createdByUserId = self::AUTHOR;
    $organization->createdAt = new DateTimeImmutable(self::PERFORMED_AT);
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function inspection(string $organizationId, ?string $interventionId): InspectionRecord
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, $organizationId);
    self::assertInstanceOf(OrganizationRecord::class, $organization);
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION;
    $inspection->organization = $organization;
    $inspection->interventionId = $interventionId;
    $inspection->equipmentId = self::EQUIPMENT;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Recorded inspector';
    $inspection->inspectorUserId = self::AUTHOR;
    $inspection->result = 'pass';
    $inspection->status = 'closed';
    $inspection->performedAt = new DateTimeImmutable(self::PERFORMED_AT);
    $inspection->createdAt = new DateTimeImmutable('2026-08-05T10:00:00+00:00');
    $inspection->updatedAt = $inspection->createdAt;
    $this->entityManager->persist($inspection);

    return $inspection;
  }
}
