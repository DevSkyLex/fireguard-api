<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Resource;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityDraftReferencesPort;
use Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict;
use Intervention\Application\Port\Outbound\InterventionPublicationGuardPort;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, InterventionWorkItemRecord};

use function array_map;
use function in_array;
use function is_string;
use function preg_match;

/**
 * Adapter InterventionDraftReferenceGuardAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionDraftReferenceGuardAdapter implements InterventionPublicationGuardPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   * @param FacilityDraftReferencesPort $drafts the discarded facility identifiers
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private FacilityDraftReferencesPort $drafts,
    private ?\Facility\Application\Port\Inbound\FacilityLifecycleReferencePort $facilities = null,
    private ?\Intervention\Application\Port\Inbound\InterventionDraftResourcesPort $draftResources = null,
    private ?\Facility\Application\Port\Inbound\FacilityHierarchyPort $hierarchy = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function beginPublication(string $organizationId, string $interventionId, array $changes): void
  {
    $this->assertPublicationReferences($organizationId, $interventionId, $interventionId);
  }

  /**
   * {@inheritDoc}
   */
  public function finishPublication(string $organizationId, string $interventionId): void
  {
    $this->assertPublicationReferences($organizationId, $interventionId, null);
  }

  /**
   * {@inheritDoc}
   */
  public function endPublication(): void
  {
  }

  /**
   * {@inheritDoc}
   */
  public function assertCanDiscard(string $interventionId, bool $interventionRetained = true): void
  {
    $ids = $this->drafts->draftIds($interventionId);
    $iris = $this->draftResources?->draftResourceIris($interventionId)
      ?? array_map(static fn (string $id): string => '/api/facilities/' . $id, $ids);
    if ([] === $iris) {
      return;
    }
    $discarded = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$discarded instanceof InterventionRecord || null === $discarded->organization) {
      return;
    }
    $organizationId = $discarded->organization->id;
    if ($this->entityManager->getConnection()->isTransactionActive()) {
      $this->hierarchy?->lock($organizationId);
    }
    $references = [];
    /** @var list<InterventionRecord> $interventions */
    $interventions = $this->entityManager->createQueryBuilder()->select('intervention')->from(InterventionRecord::class, 'intervention')
      ->where('intervention.siteId IN (:ids)')->andWhere('IDENTITY(intervention.organization) = :organization')
      ->andWhere(':retained = true OR intervention.id <> :discarded')
      ->setParameter('ids', $ids)->setParameter('organization', $organizationId)
      ->setParameter('retained', $interventionRetained)->setParameter('discarded', $interventionId)->getQuery()->getResult();
    foreach ($interventions as $record) {
      $references[] = ['resourceType' => 'intervention', 'resourceId' => $record->id, 'relatedResourceId' => $record->siteId ?? ''];
    }
    /** @var list<InterventionWorkItemRecord> $items */
    $items = $this->entityManager->createQueryBuilder()->select('item')->from(InterventionWorkItemRecord::class, 'item')
      ->innerJoin('item.intervention', 'owner')->where('IDENTITY(owner.organization) = :organization')
      ->andWhere(':retained = true OR owner.id <> :discarded')->setParameter('organization', $organizationId)
      ->setParameter('retained', $interventionRetained)->setParameter('discarded', $interventionId)->getQuery()->getResult();
    foreach ($items as $item) {
      foreach ([$item->target, $item->resultResource] as $iri) {
        if (is_string($iri) && in_array($iri, $iris, true)
          && 1 === preg_match('#^/api/(?:facilities|equipment|inspections|inspection-responses)/([^/]+)$#', $iri, $match)) {
          $references[] = ['resourceType' => 'work_item', 'resourceId' => $item->id, 'relatedResourceId' => $match[1]];
        }
      }
    }
    /** @var list<InterventionChangeRecord> $changes */
    $changes = $this->entityManager->createQueryBuilder()->select('change')->from(InterventionChangeRecord::class, 'change')
      ->innerJoin('change.intervention', 'owner')->where('IDENTITY(owner.organization) = :organization')
      ->andWhere(':retained = true OR owner.id <> :discarded')->andWhere('change.status = :proposed')
      ->setParameter('organization', $organizationId)->setParameter('retained', $interventionRetained)
      ->setParameter('discarded', $interventionId)->setParameter('proposed', 'proposed')->getQuery()->getResult();
    foreach ($changes as $change) {
      foreach ([$change->resource, $change->patch['facility'] ?? null, $change->patch['parent'] ?? null, $change->patch['equipment'] ?? null, $change->patch['inspection'] ?? null] as $iri) {
        if (is_string($iri) && in_array($iri, $iris, true)
          && 1 === preg_match('#^/api/(?:facilities|equipment|inspections|inspection-responses)/([^/]+)$#', $iri, $match)) {
          $references[] = ['resourceType' => 'change', 'resourceId' => $change->id, 'relatedResourceId' => $match[1]];
        }
      }
    }
    if ([] !== $references) {
      throw new InterventionDraftDependencyConflict($references);
    }
  }

  /**
   * @since 1.0.0
   *
   * @param string $organizationId the publication organization
   * @param string $interventionId the publication intervention
   * @param ?string $publishingId the not-yet-published facilities scope
   */
  private function assertPublicationReferences(string $organizationId, string $interventionId, ?string $publishingId): void
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if ($intervention instanceof InterventionRecord && null !== $intervention->siteId) {
      $this->facilities?->assertRetainedReference($organizationId, $intervention->siteId, null, $publishingId);
    }
    /** @var list<InterventionWorkItemRecord> $items */
    $items = $this->entityManager->getRepository(InterventionWorkItemRecord::class)->findBy(['intervention' => $interventionId]);
    foreach ($items as $item) {
      foreach ([$item->target, $item->resultResource] as $iri) {
        if (is_string($iri) && 1 === preg_match('#^/api/facilities/([^/]+)$#', $iri, $match)) {
          $this->facilities?->assertRetainedReference($organizationId, $match[1], null, $publishingId);
        }
      }
    }
  }
  // #endregion
}
