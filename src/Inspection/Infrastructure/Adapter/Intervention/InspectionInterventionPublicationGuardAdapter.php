<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Intervention;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\{FacilityDraftReferencesPort, FacilityLifecycleReferencePort};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, InspectionResponseRecord};
use Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict;
use Intervention\Application\Port\Inbound\InterventionDraftResourcesPort;
use Intervention\Application\Port\Outbound\InterventionPublicationGuardPort;

use function in_array;
use function preg_match;

/**
 * Class InspectionInterventionPublicationGuardAdapter.
 *
 * Validates inspection facility references and retained dependencies before publication or draft discard.
 *
 * @category Adapter
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionInterventionPublicationGuardAdapter implements InterventionPublicationGuardPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Uses the same main entity manager as the intervention publication transaction.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   * @param ?FacilityLifecycleReferencePort $facilities validates lifecycle references
   * @param ?FacilityDraftReferencesPort $facilityDrafts resolves draft facilities
   * @param ?InterventionDraftResourcesPort $draftResources resolves draft resources
   *
   * @return void no return value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private ?FacilityLifecycleReferencePort $facilities = null,
    private ?FacilityDraftReferencesPort $facilityDrafts = null,
    private ?InterventionDraftResourcesPort $draftResources = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method beginPublication.
   *
   * Allows draft facilities belonging to the intervention being published.
   *
   * @access public
   *
   * @param string $organizationId the publication organization
   * @param string $interventionId the publication intervention
   * @param list<array{resource: string, patch: array<string, mixed>}> $changes the ordered proposed changes
   *
   * @return void no return value
   */
  public function beginPublication(string $organizationId, string $interventionId, array $changes): void
  {
    $this->assertPublicationReferences($interventionId, $interventionId);
  }

  /**
   * Method finishPublication.
   *
   * Validates retained references after draft facilities have been published.
   *
   * @access public
   *
   * @param string $organizationId the publication organization
   * @param string $interventionId the publication intervention
   *
   * @return void no return value
   */
  public function finishPublication(string $organizationId, string $interventionId): void
  {
    $this->assertPublicationReferences($interventionId, null);
  }

  /**
   * Method endPublication.
   *
   * Completes a guard that retains no publication state.
   *
   * @access public
   *
   * @return void no return value
   */
  public function endPublication(): void
  {
    // This guard has no publication state to clear after commit or rollback.
  }

  /**
   * Method assertCanDiscard.
   *
   * Refuses removing drafts referenced by published records or another intervention's drafts.
   *
   * @access public
   *
   * @param string $interventionId the intervention being discarded
   * @param bool $interventionRetained whether its own site and work items remain after discard
   *
   * @return void no return value
   *
   * @throws InterventionDraftDependencyConflict when retained records reference the discarded drafts
   */
  public function assertCanDiscard(string $interventionId, bool $interventionRetained = true): void
  {
    $facilityIds = $this->facilityDrafts?->draftIds($interventionId) ?? [];
    $resourceIds = $this->draftResourceIds($interventionId);
    if ([] === $facilityIds && [] === $resourceIds['equipment'] && [] === $resourceIds['inspection']) {
      return;
    }

    $references = [
      ...$this->inspectionDependencies($interventionId, $facilityIds, $resourceIds['equipment']),
      ...$this->responseDependencies($interventionId, $resourceIds['inspection']),
    ];
    if ([] !== $references) {
      throw new InterventionDraftDependencyConflict($references);
    }
  }

  /**
   * Method assertPublicationReferences.
   *
   * Keeps terminal inspection history while requiring active facilities for ongoing inspections.
   *
   * @access public
   *
   * @param string $interventionId the owning intervention
   * @param ?string $publishingId its pending facility publication scope
   *
   * @return void no return value
   */
  public function assertPublicationReferences(string $interventionId, ?string $publishingId): void
  {
    if (null === $this->facilities) {
      return;
    }
    /** @var list<InspectionRecord> $records */
    $records = $this->entityManager->getRepository(InspectionRecord::class)->findBy(['interventionId' => $interventionId]);
    foreach ($records as $record) {
      if (null !== $record->facilityId) {
        if (in_array($record->status, ['closed', 'cancelled'], true)) {
          $this->facilities->assertRetainedReference($record->organizationId(), $record->facilityId, null, $publishingId);
        } else {
          $this->facilities->assertReference($record->organizationId(), $record->facilityId, null, $publishingId);
        }
      }
    }
  }

  /**
   * Method draftResourceIds.
   *
   * Selects the equipment and inspection draft targets whose dependents this module owns.
   *
   * @access private
   *
   * @param string $interventionId the intervention being discarded
   *
   * @return array{equipment: list<string>, inspection: list<string>} draft identifiers by resource type
   */
  private function draftResourceIds(string $interventionId): array
  {
    $iris = $this->draftResources?->draftResourceIris($interventionId) ?? [];
    $equipmentIds = [];
    $inspectionIds = [];
    foreach ($iris as $iri) {
      if (1 === preg_match('#^/api/equipment/([^/]+)$#', $iri, $match)) {
        $equipmentIds[] = $match[1];
      } elseif (1 === preg_match('#^/api/inspections/([^/]+)$#', $iri, $match)) {
        $inspectionIds[] = $match[1];
      }
    }

    return ['equipment' => $equipmentIds, 'inspection' => $inspectionIds];
  }

  /**
   * Method inspectionDependencies.
   *
   * Reports retained inspections referencing a discarded facility or equipment draft.
   *
   * @access private
   *
   * @param string $interventionId the intervention being discarded
   * @param list<string> $facilityIds discarded facility identifiers
   * @param list<string> $equipmentIds discarded equipment identifiers
   *
   * @return list<array{resourceType: string, resourceId: string, relatedResourceId: string}> retained dependencies
   */
  private function inspectionDependencies(string $interventionId, array $facilityIds, array $equipmentIds): array
  {
    /** @var list<InspectionRecord> $records */
    $records = $this->entityManager->createQueryBuilder()->select('inspection')->from(InspectionRecord::class, 'inspection')
      ->where('inspection.facilityId IN (:ids) OR inspection.equipmentId IN (:equipmentIds)')
      ->setParameter('ids', $facilityIds)->setParameter('equipmentIds', $equipmentIds)->getQuery()->getResult();
    $references = [];
    foreach ($records as $record) {
      if ('draft' !== $record->recordStatus || $record->interventionId !== $interventionId) {
        $references[] = ['resourceType' => 'inspection', 'resourceId' => $record->id, 'relatedResourceId' => in_array($record->equipmentId, $equipmentIds, true) ? $record->equipmentId : ($record->facilityId ?? '')];
      }
    }

    return $references;
  }

  /**
   * Method responseDependencies.
   *
   * Reports retained responses referencing a discarded inspection draft.
   *
   * @access private
   *
   * @param string $interventionId the intervention being discarded
   * @param list<string> $inspectionIds discarded inspection identifiers
   *
   * @return list<array{resourceType: string, resourceId: string, relatedResourceId: string}> retained dependencies
   */
  private function responseDependencies(string $interventionId, array $inspectionIds): array
  {
    /** @var list<InspectionResponseRecord> $responses */
    $responses = $this->entityManager->createQueryBuilder()->select('response')->from(InspectionResponseRecord::class, 'response')
      ->where('response.inspectionId IN (:ids)')->setParameter('ids', $inspectionIds)->getQuery()->getResult();
    $references = [];
    foreach ($responses as $response) {
      if ('draft' !== $response->recordStatus || $response->interventionId !== $interventionId) {
        $references[] = ['resourceType' => 'inspection_response', 'resourceId' => $response->id, 'relatedResourceId' => $response->inspectionId];
      }
    }

    return $references;
  }
  // #endregion
}
