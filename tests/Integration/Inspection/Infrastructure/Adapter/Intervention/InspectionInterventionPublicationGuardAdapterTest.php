<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\{FacilityDraftReferencesPort, FacilityLifecycleReferencePort};
use Inspection\Infrastructure\Adapter\Intervention\InspectionInterventionPublicationGuardAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, InspectionResponseRecord};
use Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict;
use Intervention\Application\Port\Inbound\InterventionDraftResourcesPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_column;

/**
 * Class InspectionInterventionPublicationGuardAdapterTest.
 *
 * Verifies the publication scope and complete retained-dependency payload against PostgreSQL.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionInterventionPublicationGuardAdapter::class)]
final class InspectionInterventionPublicationGuardAdapterTest extends KernelTestCase
{
  // #region Properties
  /**
   * Constant ORGANIZATION_ID.
   */
  private const string ORGANIZATION_ID = '19ae8400-e29b-41d4-a716-44665519a001';

  /**
   * Constant INTERVENTION_ID.
   */
  private const string INTERVENTION_ID = '19ae8400-e29b-41d4-a716-44665519a002';

  /**
   * Constant FACILITY_ID.
   */
  private const string FACILITY_ID = '19ae8400-e29b-41d4-a716-44665519a003';

  /**
   * Constant EQUIPMENT_ID.
   */
  private const string EQUIPMENT_ID = '19ae8400-e29b-41d4-a716-44665519a004';

  /**
   * Constant INSPECTION_ID.
   */
  private const string INSPECTION_ID = '19ae8400-e29b-41d4-a716-44665519a005';

  /**
   * Property entityManager.
   */
  private EntityManagerInterface $entityManager;

  /**
   * Property organization.
   */
  private OrganizationRecord $organization;
  // #endregion

  // #region Methods
  /**
   * Method setUp.
   *
   * Creates this test's organization on the isolated main database.
   *
   * @access protected
   *
   * @return void no return value
   */
  protected function setUp(): void
  {
    self::bootKernel();
    $entityManager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
    $this->entityManager = $entityManager;
    $this->organization = new OrganizationRecord();
    $this->organization->id = self::ORGANIZATION_ID;
    $this->organization->name = 'Inspection publication guard';
    $this->organization->slug = 'inspection-publication-guard';
    $this->organization->ownerUserId = self::ORGANIZATION_ID;
    $this->organization->createdByUserId = self::ORGANIZATION_ID;
    $this->organization->createdAt = new DateTimeImmutable();
    $this->organization->updatedAt = $this->organization->createdAt;
    $this->entityManager->persist($this->organization);
    $this->entityManager->flush();
  }

  /**
   * Method tearDown.
   *
   * Releases the main entity manager after the transactional test.
   *
   * @access protected
   *
   * @return void no return value
   */
  protected function tearDown(): void
  {
    parent::tearDown();
    if ($this->entityManager->isOpen()) {
      $this->entityManager->close();
    }
  }

  /**
   * Method publicationStates.
   *
   * Covers every inspection lifecycle state before and after facility publication.
   *
   * @access public
   *
   * @return iterable<string, array{string, bool, bool}> status, historical-reference flag and publication phase
   */
  public static function publicationStates(): iterable
  {
    foreach (['draft' => false, 'submitted' => false, 'closed' => true, 'cancelled' => true] as $status => $retained) {
      yield $status . ' before publication' => [$status, $retained, true];
      yield $status . ' after publication' => [$status, $retained, false];
    }
  }

  /**
   * Method itPreservesReferenceStrictnessAndPublicationScope.
   *
   * Requires active facilities for ongoing inspections and retained references for terminal history.
   *
   * @access public
   *
   * @param string $status the inspection lifecycle state
   * @param bool $retained whether the relation is historical
   * @param bool $begin whether this is the pre-publication phase
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('publicationStates')]
  public function itPreservesReferenceStrictnessAndPublicationScope(string $status, bool $retained, bool $begin): void
  {
    $this->inspection(self::INSPECTION_ID, self::INTERVENTION_ID, 'draft', $status);
    $this->entityManager->flush();
    $facilities = $this->createMock(FacilityLifecycleReferencePort::class);
    $expectedMethod = $retained ? 'assertRetainedReference' : 'assertReference';
    $otherMethod = $retained ? 'assertReference' : 'assertRetainedReference';
    $facilities->expects(self::once())->method($expectedMethod)
      ->with(self::ORGANIZATION_ID, self::FACILITY_ID, null, $begin ? self::INTERVENTION_ID : null);
    $facilities->expects(self::never())->method($otherMethod);
    $guard = new InspectionInterventionPublicationGuardAdapter($this->entityManager, $facilities);

    if ($begin) {
      $guard->beginPublication(self::ORGANIZATION_ID, self::INTERVENTION_ID, []);
    } else {
      $guard->finishPublication(self::ORGANIZATION_ID, self::INTERVENTION_ID);
    }
    $guard->endPublication();
  }

  /**
   * Method itKeepsOwnDraftDependenciesTogether.
   *
   * Allows discarding inspections and responses that belong to the same draft intervention.
   *
   * @access public
   *
   * @return void no return value
   */
  #[Test]
  public function itKeepsOwnDraftDependenciesTogether(): void
  {
    $this->inspection(self::INSPECTION_ID, self::INTERVENTION_ID, 'draft');
    $this->response('19ae8400-e29b-41d4-a716-44665519a006', self::INTERVENTION_ID, 'draft');
    $this->entityManager->flush();

    $this->draftGuard()->assertCanDiscard(self::INTERVENTION_ID, false);
  }

  /**
   * Method itReportsEveryRetainedDependencyWithTheOriginalTargetPriority.
   *
   * Preserves equipment target priority and includes published and foreign-draft records of both types.
   *
   * @access public
   *
   * @return void no return value
   */
  #[Test]
  public function itReportsEveryRetainedDependencyWithTheOriginalTargetPriority(): void
  {
    $foreignIntervention = '19ae8400-e29b-41d4-a716-44665519a099';
    $inspection = $this->inspection('19ae8400-e29b-41d4-a716-44665519a007', self::INTERVENTION_ID, 'published');
    $inspection->equipmentId = '19ae8400-e29b-41d4-a716-44665519a098';
    $this->inspection('19ae8400-e29b-41d4-a716-44665519a008', $foreignIntervention, 'draft');
    $this->response('19ae8400-e29b-41d4-a716-44665519a009', self::INTERVENTION_ID, 'published');
    $this->response('19ae8400-e29b-41d4-a716-44665519a010', $foreignIntervention, 'draft');
    $this->entityManager->flush();

    try {
      $this->draftGuard()->assertCanDiscard(self::INTERVENTION_ID);
      self::fail('Retained dependencies must prevent removing their draft targets.');
    } catch (InterventionDraftDependencyConflict $exception) {
      self::assertEqualsCanonicalizing([
        ['resourceType' => 'inspection', 'resourceId' => '19ae8400-e29b-41d4-a716-44665519a007', 'relatedResourceId' => self::FACILITY_ID],
        ['resourceType' => 'inspection', 'resourceId' => '19ae8400-e29b-41d4-a716-44665519a008', 'relatedResourceId' => self::EQUIPMENT_ID],
        ['resourceType' => 'inspection_response', 'resourceId' => '19ae8400-e29b-41d4-a716-44665519a009', 'relatedResourceId' => self::INSPECTION_ID],
        ['resourceType' => 'inspection_response', 'resourceId' => '19ae8400-e29b-41d4-a716-44665519a010', 'relatedResourceId' => self::INSPECTION_ID],
      ], $exception->references);
      self::assertSame(['inspection', 'inspection', 'inspection_response', 'inspection_response'], array_column($exception->references, 'resourceType'));
    }
  }

  /**
   * Method itSkipsQueriesWithoutRelevantDraftTargets.
   *
   * Ignores response IRIs and malformed equipment paths before querying dependencies.
   *
   * @access public
   *
   * @return void no return value
   */
  #[Test]
  public function itSkipsQueriesWithoutRelevantDraftTargets(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('createQueryBuilder');
    $draftResources = $this->createMock(InterventionDraftResourcesPort::class);
    $draftResources->expects(self::once())->method('draftResourceIris')->with(self::INTERVENTION_ID)
      ->willReturn(['/api/inspection-responses/ignored', '/api/equipment/ignored/nested']);
    $guard = new InspectionInterventionPublicationGuardAdapter($entityManager, draftResources: $draftResources);

    $guard->assertCanDiscard(self::INTERVENTION_ID);
  }

  /**
   * Method itResolvesThePublicationGuardOnTheMainEntityManager.
   *
   * Exercises the registered guard against the inspection mapping rather than the auth manager.
   *
   * @access public
   *
   * @return void no return value
   */
  #[Test]
  public function itResolvesThePublicationGuardOnTheMainEntityManager(): void
  {
    $inspection = $this->inspection(self::INSPECTION_ID, self::INTERVENTION_ID, 'draft');
    $inspection->facilityId = null;
    $this->entityManager->flush();
    $guard = self::getContainer()->get(InspectionInterventionPublicationGuardAdapter::class);
    self::assertInstanceOf(InspectionInterventionPublicationGuardAdapter::class, $guard);

    $guard->assertPublicationReferences(self::INTERVENTION_ID, null);
  }

  /**
   * Method draftGuard.
   *
   * Supplies all three kinds of discarded targets while querying the real main database.
   *
   * @access private
   *
   * @return InspectionInterventionPublicationGuardAdapter the draft dependency guard
   */
  private function draftGuard(): InspectionInterventionPublicationGuardAdapter
  {
    $facilityDrafts = $this->createMock(FacilityDraftReferencesPort::class);
    $facilityDrafts->expects(self::once())->method('draftIds')->with(self::INTERVENTION_ID)->willReturn([self::FACILITY_ID]);
    $draftResources = $this->createMock(InterventionDraftResourcesPort::class);
    $draftResources->expects(self::once())->method('draftResourceIris')->with(self::INTERVENTION_ID)
      ->willReturn(['/api/equipment/' . self::EQUIPMENT_ID, '/api/inspections/' . self::INSPECTION_ID]);

    return new InspectionInterventionPublicationGuardAdapter($this->entityManager, facilityDrafts: $facilityDrafts, draftResources: $draftResources);
  }

  /**
   * Method inspection.
   *
   * Persists an inspection that references both discarded equipment and facility targets by default.
   *
   * @access private
   *
   * @param string $id the inspection identifier
   * @param string $interventionId the owning intervention
   * @param string $recordStatus the publication state
   * @param string $status the inspection lifecycle state
   *
   * @return InspectionRecord the persisted inspection
   */
  private function inspection(string $id, string $interventionId, string $recordStatus, string $status = 'draft'): InspectionRecord
  {
    $record = new InspectionRecord();
    $record->id = $id;
    $record->organization = $this->organization;
    $record->interventionId = $interventionId;
    $record->recordStatus = $recordStatus;
    $record->facilityId = self::FACILITY_ID;
    $record->equipmentId = self::EQUIPMENT_ID;
    $record->inspectorType = 'user';
    $record->inspectorName = 'Publication inspector';
    $record->result = 'pass';
    $record->status = $status;
    $record->performedAt = new DateTimeImmutable();
    $record->createdAt = $record->performedAt;
    $record->updatedAt = $record->performedAt;
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * Method response.
   *
   * Persists a response targeting the inspection draft being discarded.
   *
   * @access private
   *
   * @param string $id the response identifier
   * @param string $interventionId the owning intervention
   * @param string $recordStatus the publication state
   *
   * @return void no return value
   */
  private function response(string $id, string $interventionId, string $recordStatus): void
  {
    $record = new InspectionResponseRecord();
    $record->id = $id;
    $record->organization = $this->organization;
    $record->interventionId = $interventionId;
    $record->recordStatus = $recordStatus;
    $record->inspectionId = self::INSPECTION_ID;
    $record->itemKey = 'pressure';
    $record->value = true;
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }
  // #endregion
}
