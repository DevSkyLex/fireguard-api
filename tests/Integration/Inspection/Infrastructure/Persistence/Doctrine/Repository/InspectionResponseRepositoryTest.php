<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, InspectionResponseId, InspectionResponseStatus};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Repository\{InspectionRepository, InspectionResponseRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InspectionResponseRepositoryTest.
 *
 * Exercises the canonical inspection-response persistence against a real
 * database, plus `InspectionRepository::findScope()` — a scalar DQL projection
 * over `IDENTITY(i.organization)` and the canonical `intervention_id` column,
 * which nothing but a real query can prove.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionResponseRepository::class)]
#[CoversClass(InspectionRepository::class)]
final class InspectionResponseRepositoryTest extends KernelTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655462001';

  private const string INSPECTION_ID = '660e8400-e29b-41d4-a716-446655462002';

  private const string SCOPED_INSPECTION_ID = '660e8400-e29b-41d4-a716-446655462003';

  private const string INTERVENTION_ID = '660e8400-e29b-41d4-a716-446655462004';

  private const string RESPONSE_ID = '660e8400-e29b-41d4-a716-446655462005';

  private const string ABSENT_INSPECTION_ID = '660e8400-e29b-41d4-a716-4466554620ff';

  private const string CLIENT_ID = '660e8400-e29b-41d4-a716-446655462006';
  // #endregion

  // #region Properties
  private EntityManagerInterface $entityManager;

  private InspectionResponseRepository $repository;

  private InspectionRepository $inspections;
  // #endregion

  // #region Fixture
  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new InspectionResponseRepository($this->entityManager);
    /** @var InspectionRepository $inspections */
    $inspections = static::getContainer()->get(InspectionRepository::class);
    $this->inspections = $inspections;

    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Inspection Response Repository Test';
    $organization->slug = 'inspection-response-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655469001';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655469001';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $this->entityManager->persist($organization);

    foreach ([self::INSPECTION_ID => null, self::SCOPED_INSPECTION_ID => self::INTERVENTION_ID] as $id => $interventionId) {
      $inspection = new InspectionRecord();
      $inspection->id = $id;
      $inspection->organization = $organization;
      $inspection->interventionId = $interventionId;
      $inspection->equipmentId = '660e8400-e29b-41d4-a716-446655468889';
      $inspection->inspectorType = 'user';
      $inspection->inspectorName = 'Jane Doe';
      $inspection->result = 'pass';
      $inspection->status = 'draft';
      $inspection->performedAt = $now;
      $inspection->createdAt = $now;
      $inspection->updatedAt = $now;
      $this->entityManager->persist($inspection);
    }

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  /**
   * Method testSaveThenFindByIdRoundTripsAResponse.
   *
   * @return void no return value
   */
  #[Test]
  public function testSaveThenFindByIdRoundTripsAResponse(): void
  {
    $this->repository->save($this->response());

    $found = $this->repository->findById(InspectionResponseId::fromString(self::RESPONSE_ID));

    self::assertNotNull($found);
    self::assertSame(self::ORGANIZATION_ID, (string) $found->organizationId());
    self::assertSame(self::SCOPED_INSPECTION_ID, (string) $found->inspectionId());
    self::assertSame(self::INTERVENTION_ID, $found->interventionId());
    self::assertSame(self::CLIENT_ID, $found->clientId());
    self::assertSame(InspectionResponseStatus::DRAFT, $found->status());
    self::assertSame(1, $found->revision());
    self::assertSame(['ok' => true], $found->value());
  }

  /**
   * Method testSaveOnAnExistingIdentifierUpdatesInPlace.
   *
   * The offline routes replay the same id, so a second `save()` must update
   * rather than raise a duplicate-key error.
   *
   * @return void no return value
   */
  #[Test]
  public function testSaveOnAnExistingIdentifierUpdatesInPlace(): void
  {
    $response = $this->response();
    $this->repository->save($response);

    $response->updateValue(['ok' => false]);
    $this->repository->save($response);
    $this->entityManager->clear();

    $found = $this->repository->findById(InspectionResponseId::fromString(self::RESPONSE_ID));

    self::assertNotNull($found);
    self::assertSame(2, $found->revision());
    self::assertSame(['ok' => false], $found->value());
  }

  /**
   * Method testExistsByClientIdSeesAStoredKeyAndOnlyThat.
   *
   * @return void no return value
   */
  #[Test]
  public function testExistsByClientIdSeesAStoredKeyAndOnlyThat(): void
  {
    self::assertFalse($this->repository->existsByClientId(self::CLIENT_ID));

    $this->repository->save($this->response());

    self::assertTrue($this->repository->existsByClientId(self::CLIENT_ID));
    self::assertFalse($this->repository->existsByClientId(self::RESPONSE_ID));
  }

  /**
   * Method testDeleteRemovesTheRowAndIsIdempotent.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteRemovesTheRowAndIsIdempotent(): void
  {
    $this->repository->save($this->response());
    $id = InspectionResponseId::fromString(self::RESPONSE_ID);

    $this->repository->delete($id);
    self::assertNull($this->repository->findById($id));

    $this->repository->delete($id);
    self::assertNull($this->repository->findById($id));
  }

  /**
   * Method testFindScopeProjectsTheOwningOrganizationAndIntervention.
   *
   * @return void no return value
   */
  #[Test]
  public function testFindScopeProjectsTheOwningOrganizationAndIntervention(): void
  {
    $scoped = $this->inspections->findScope(InspectionId::fromString(self::SCOPED_INSPECTION_ID));

    self::assertNotNull($scoped);
    self::assertSame(self::SCOPED_INSPECTION_ID, $scoped->inspectionId);
    self::assertSame(self::ORGANIZATION_ID, $scoped->organizationId);
    self::assertSame(self::INTERVENTION_ID, $scoped->interventionId);

    $unscoped = $this->inspections->findScope(InspectionId::fromString(self::INSPECTION_ID));

    self::assertNotNull($unscoped);
    self::assertNull($unscoped->interventionId);
  }

  /**
   * Method testFindScopeAnswersNullForAnAbsentInspection.
   *
   * @return void no return value
   */
  #[Test]
  public function testFindScopeAnswersNullForAnAbsentInspection(): void
  {
    self::assertNull($this->inspections->findScope(InspectionId::fromString(self::ABSENT_INSPECTION_ID)));
  }
  // #endregion

  // #region Helpers
  /**
   * Method response.
   *
   * @return InspectionResponse a draft response tied to the scoped inspection
   */
  private function response(): InspectionResponse
  {
    return InspectionResponse::create(
      id: InspectionResponseId::fromString(self::RESPONSE_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      inspectionId: InspectionId::fromString(self::SCOPED_INSPECTION_ID),
      itemKey: 'pressure',
      value: ['ok' => true],
      interventionId: self::INTERVENTION_ID,
      clientId: self::CLIENT_ID,
    );
  }

  /**
   * Method cleanup.
   */
  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM inspection_responses WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM inspections WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
  // #endregion
}
