<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Intervention;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
use Facility\Application\Port\Inbound\{FacilityArchivalGuardPort, FacilityDraftReferencesPort, FacilityHierarchyPort};
use Facility\Application\Service\FacilityHierarchyPublicationContext;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict;
use Intervention\Application\Port\Outbound\InterventionPublicationGuardPort;
use InvalidArgumentException;

use function array_key_exists;
use function array_map;
use function array_values;
use function is_string;
use function preg_match;

/**
 * Adapter FacilityPublicationGuardAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityPublicationGuardAdapter implements InterventionPublicationGuardPort, FacilityDraftReferencesPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   * @param FacilityHierarchyPort $hierarchy the shared relation policy
   * @param FacilityHierarchyPublicationContext $context the scoped merged validation
   * @param FacilityArchivalGuardPort $archivalGuard the final retained-dependent policy
   */
  public function __construct(private EntityManagerInterface $entityManager, private FacilityHierarchyPort $hierarchy, private FacilityHierarchyPublicationContext $context, private FacilityArchivalGuardPort $archivalGuard)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function beginPublication(string $organizationId, string $interventionId, array $changes): void
  {
    $this->hierarchy->lock($organizationId);
    $nodes = [];
    $originalNodes = [];
    /** @var list<FacilityRecord> $records */
    $records = $this->entityManager->getRepository(FacilityRecord::class)->findBy(['interventionId' => $interventionId, 'recordStatus' => 'draft']);
    foreach ($records as $record) {
      if ($record->organization?->id !== $organizationId) {
        throw new InvalidArgumentException('Publication facility organization is invalid.');
      }
      $nodes[$record->id] = $this->node($record);
    }
    foreach ($changes as $change) {
      if (1 !== preg_match('#^/api/facilities/([^/]+)$#', $change['resource'], $match)) {
        continue;
      }
      $patch = $change['patch'];
      if (!array_key_exists('type', $patch) && !array_key_exists('parent', $patch) && !array_key_exists('status', $patch)) {
        continue;
      }
      $record = $this->entityManager->find(FacilityRecord::class, $match[1]);
      if (!$record instanceof FacilityRecord || $record->organization?->id !== $organizationId || 'published' !== $record->recordStatus) {
        throw new InvalidArgumentException('Publication facility target is invalid.');
      }
      $originalNodes[$record->id] ??= $this->node($record);
      $previous = $nodes[$record->id] ?? $this->node($record);
      $type = $patch['type'] ?? $previous->type;
      $status = $patch['status'] ?? $previous->status;
      if (!is_string($type) || !is_string($status)) {
        throw new InvalidArgumentException('Publication hierarchy fields must be strings.');
      }
      $parentId = $previous->parentFacilityId;
      if (array_key_exists('parent', $patch)) {
        $parentId = $this->parentId($patch['parent']);
      }
      $nodes[$record->id] = new FacilityHierarchyNode($record->id, $type, $parentId, $status);
    }
    $this->hierarchy->assertGraph($organizationId, array_values($nodes), $originalNodes);
    $archives = [];
    foreach ($nodes as $id => $node) {
      $record = $this->entityManager->find(FacilityRecord::class, $id);
      if ('archived' === $node->status && $record instanceof FacilityRecord
        && ('archived' !== $record->status || 'draft' === $record->recordStatus)) {
        $archives[] = $id;
      }
    }
    $this->context->enter($organizationId, array_values($nodes), $archives, $originalNodes);
  }

  /**
   * {@inheritDoc}
   */
  public function finishPublication(string $organizationId, string $interventionId): void
  {
    $nodes = [];
    foreach ($this->context->ids() as $id) {
      $record = $this->entityManager->find(FacilityRecord::class, $id);
      if ($record instanceof FacilityRecord && $record->organization?->id === $organizationId) {
        $nodes[] = $this->node($record);
      }
    }
    $this->hierarchy->assertGraph($organizationId, $nodes, $this->context->originalNodes());
    foreach ($this->context->archivedTransitions() as $id) {
      $this->archivalGuard->assertNoActiveDependents($organizationId, $id);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function endPublication(): void
  {
    $this->context->leave();
  }

  /**
   * {@inheritDoc}
   */
  public function draftIds(string $interventionId): array
  {
    /** @var list<FacilityRecord> $records */
    $records = $this->entityManager->getRepository(FacilityRecord::class)->findBy(['interventionId' => $interventionId, 'recordStatus' => 'draft']);

    return array_map(static fn (FacilityRecord $record): string => $record->id, $records);
  }

  /**
   * {@inheritDoc}
   */
  public function assertCanDiscard(string $interventionId, bool $interventionRetained = true): void
  {
    $ids = $this->draftIds($interventionId);
    if ([] === $ids) {
      return;
    }
    $first = $this->entityManager->find(FacilityRecord::class, $ids[0]);
    if ($first instanceof FacilityRecord && $this->entityManager->getConnection()->isTransactionActive()) {
      $this->hierarchy->lock($first->organizationId());
    }
    /** @var list<FacilityRecord> $children */
    $children = $this->entityManager->createQueryBuilder()->select('facility')->from(FacilityRecord::class, 'facility')
      ->where('IDENTITY(facility.parentFacility) IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult();
    $references = [];
    foreach ($children as $child) {
      if ('draft' !== $child->recordStatus || $child->interventionId !== $interventionId) {
        $references[] = ['resourceType' => 'facility', 'resourceId' => $child->id, 'relatedResourceId' => $this->node($child)->parentFacilityId ?? ''];
      }
    }
    if ([] !== $references) {
      throw new InterventionDraftDependencyConflict($references);
    }
  }

  /**
   * @since 1.0.0
   *
   * @param FacilityRecord $record the facility final state
   *
   * @return FacilityHierarchyNode the post-publication scalar graph node
   */
  private function node(FacilityRecord $record): FacilityHierarchyNode
  {
    return new FacilityHierarchyNode($record->id, $record->type, $record->parentFacility?->id, $record->status);
  }

  /**
   * @since 1.0.0
   *
   * @param mixed $iri the proposed parent
   *
   * @return ?string the parsed parent identifier
   */
  private function parentId(mixed $iri): ?string
  {
    if (null === $iri) {
      return null;
    }
    if (!is_string($iri) || 1 !== preg_match('#^/api/facilities/([^/]+)$#', $iri, $matches)) {
      throw new InvalidArgumentException('Publication parent must be a facility IRI or null.');
    }

    return $matches[1];
  }

  // #endregion
}
