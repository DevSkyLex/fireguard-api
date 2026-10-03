<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Intervention;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Application\Port\Inbound\{FacilityDraftReferencesPort, FacilityLifecycleReferencePort};
use Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict;
use Intervention\Application\Port\Outbound\InterventionPublicationGuardPort;
use InvalidArgumentException;

use function array_key_exists;
use function is_string;
use function preg_match;

/**
 * Adapter EquipmentPublicationGuardAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentPublicationGuardAdapter implements InterventionPublicationGuardPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   * @param FacilityValidationPort $facilities the facility reference policy
   * @param FacilityDraftReferencesPort $drafts the facility drafts owned by the discarded intervention
   * @param FacilityLifecycleReferencePort $retainedReferences the scope policy for terminal historical assignments
   */
  public function __construct(private EntityManagerInterface $entityManager, private FacilityValidationPort $facilities, private FacilityDraftReferencesPort $drafts, private FacilityLifecycleReferencePort $retainedReferences)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function beginPublication(string $organizationId, string $interventionId, array $changes): void
  {
    /** @var list<EquipmentRecord> $records */
    $records = $this->entityManager->getRepository(EquipmentRecord::class)->findBy(['interventionId' => $interventionId, 'recordStatus' => 'draft']);
    foreach ($records as $record) {
      if ($record->organization?->id !== $organizationId) {
        throw new InvalidArgumentException('Publication equipment organization is invalid.');
      }
      if (null !== $record->facilityId) {
        if ('decommissioned' === $record->status) {
          $this->retainedReferences->assertRetainedReference($organizationId, $record->facilityId, null, $interventionId);
        } else {
          $this->facilities->assertFacilityIsAssignable($record->facilityId, $organizationId, null, $interventionId);
        }
      }
    }
    foreach ($changes as $change) {
      if (1 !== preg_match('#^/api/equipment/([^/]+)$#', $change['resource'])
        || !array_key_exists('facility', $change['patch']) || null === $change['patch']['facility']) {
        continue;
      }
      $iri = $change['patch']['facility'];
      if (!is_string($iri) || 1 !== preg_match('#^/api/facilities/([^/]+)$#', $iri, $facility)) {
        throw new InvalidArgumentException('Publication equipment facility must be an IRI or null.');
      }
      $this->facilities->assertFacilityIsAssignable($facility[1], $organizationId, null, $interventionId);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function finishPublication(string $organizationId, string $interventionId): void
  {
    /** @var list<EquipmentRecord> $records */
    $records = $this->entityManager->getRepository(EquipmentRecord::class)->findBy(['interventionId' => $interventionId]);
    foreach ($records as $record) {
      if (null !== $record->facilityId && $record->organization?->id === $organizationId) {
        if ('decommissioned' === $record->status) {
          $this->retainedReferences->assertRetainedReference($organizationId, $record->facilityId);
        } else {
          $this->facilities->assertFacilityIsAssignable($record->facilityId, $organizationId);
        }
      }
    }
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
    if ([] === $ids) {
      return;
    }
    /** @var list<EquipmentRecord> $records */
    $records = $this->entityManager->createQueryBuilder()->select('equipment')->from(EquipmentRecord::class, 'equipment')
      ->where('equipment.facilityId IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult();
    $references = [];
    foreach ($records as $record) {
      if ('draft' !== $record->recordStatus || $record->interventionId !== $interventionId) {
        $references[] = ['resourceType' => 'equipment', 'resourceId' => $record->id, 'relatedResourceId' => $record->facilityId ?? ''];
      }
    }
    if ([] !== $references) {
      throw new InterventionDraftDependencyConflict($references);
    }
  }
  // #endregion
}
