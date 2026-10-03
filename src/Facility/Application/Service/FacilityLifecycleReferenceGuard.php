<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Port\Inbound\FacilityLifecycleReferencePort;
use Facility\Application\Port\Outbound\FacilityHierarchySnapshotPort;
use InvalidArgumentException;

use function in_array;

/**
 * Service FacilityLifecycleReferenceGuard.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityLifecycleReferenceGuard implements FacilityLifecycleReferencePort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param FacilityHierarchySnapshotPort $snapshot the owner-scoped lifecycle facts
   * @param FacilityHierarchyPublicationContext $publication the validated future publication graph
   */
  public function __construct(private FacilityHierarchySnapshotPort $snapshot, private FacilityHierarchyPublicationContext $publication)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assertReference(string $organizationId, string $facilityId, ?string $draftInterventionId = null, ?string $publishingInterventionId = null): void
  {
    $this->validateReference($organizationId, $facilityId, $draftInterventionId, $publishingInterventionId, true);
  }

  /**
   * {@inheritDoc}
   */
  public function assertRetainedReference(string $organizationId, string $facilityId, ?string $draftInterventionId = null, ?string $publishingInterventionId = null): void
  {
    $this->validateReference($organizationId, $facilityId, $draftInterventionId, $publishingInterventionId, false);
  }

  /**
   * Method validateReference.
   *
   * Preserves organization and publication scope for both new and retained references.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization
   * @param string $facilityId the referenced facility
   * @param ?string $draftInterventionId the owning intervention for a draft resource
   * @param ?string $publishingInterventionId the intervention being published
   * @param bool $requiresActive whether this is a new active relation
   *
   * @return void
   */
  private function validateReference(string $organizationId, string $facilityId, ?string $draftInterventionId, ?string $publishingInterventionId, bool $requiresActive): void
  {
    $merged = $this->publication->node($organizationId, $facilityId);
    $node = $merged ?? ($this->snapshot->load($organizationId)[$facilityId] ?? null);
    if (null === $node || ($requiresActive && 'active' !== $node->status)) {
      throw new InvalidArgumentException('The referenced facility is unavailable in this organization.');
    }
    if ('published' !== $node->publicationState
      && ('draft' !== $node->publicationState || null === $node->interventionId
        || !in_array($node->interventionId, [$draftInterventionId, $publishingInterventionId], true))) {
      throw new InvalidArgumentException('Published resources require a published facility; a draft facility must belong to the same intervention.');
    }
  }
  // #endregion
}
