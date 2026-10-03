<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Service\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Domain\Exception\{FacilityHasActiveDependentsException, FacilityHierarchyException, FacilityMetadataValidationException};
use Facility\Domain\ValueObject\{FacilityFloorMetrics, FacilityId, FacilityType, PlanGeometry};
use Facility\Infrastructure\Exception\FacilityPatchConflictException;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Shared\Domain\Exception\InvalidValueException;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function sprintf;
use function trim;

/**
 * Class FacilityInterventionPatchApplier
 *
 * Applies and validates proposed changes to one published facility row.
 *
 * @category Service
 */
final readonly class FacilityInterventionPatchApplier
{
  // #region Constants
  /**
   * Constant PATCHABLE_FIELDS.
   *
   * Facility fields accepted in an intervention draft patch.
   *
   * @access private
   *
   * @var list<string>
   */
  private const PATCHABLE_FIELDS = ['type', 'name', 'code', 'address', 'metadata', 'status', 'parent', 'latitude', 'longitude', 'planGeometry', 'elevationMeters', 'heightMeters'];

  /**
   * Constant STATUSES.
   *
   * Facility statuses accepted by this patch adapter.
   *
   * @access private
   *
   * @var list<string>
   */
  private const STATUSES = ['active', 'archived'];

  /**
   * Constant TYPES.
   *
   * Facility types accepted by this patch adapter.
   *
   * @access private
   *
   * @var list<string>
   */
  private const TYPES = ['site', 'building', 'floor', 'zone', 'area'];

  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Coordinates facility patch changes with hierarchy, archival, metadata, and floor-plan attachment rules.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager loads published facility records
   * @param FacilityArchivalGuardPort $archivalGuard checks active descendants before archival
   * @param FacilityRepositoryPort $facilityRepository reads facility hierarchy
   * @param FacilityMetadataSchemaGuard $metadataSchemaGuard validates metadata changes
   * @param \Facility\Application\Port\Outbound\FacilityAttachmentRepositoryPort $attachments resolves floor-plan attachments
   * @param \Facility\Application\Service\FacilityAttachmentAncestryGuard $planAncestry checks attachment ancestry
   * @param int $maxDepth maximum allowed facility hierarchy depth
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private FacilityArchivalGuardPort $archivalGuard,
    private FacilityRepositoryPort $facilityRepository,
    private FacilityMetadataSchemaGuard $metadataSchemaGuard,
    private \Facility\Application\Port\Outbound\FacilityAttachmentRepositoryPort $attachments,
    private \Facility\Application\Service\FacilityAttachmentAncestryGuard $planAncestry,
    private int $maxDepth,
    private ?\Facility\Application\Port\Inbound\FacilityHierarchyPort $hierarchy = null,
    private ?\Facility\Application\Service\FacilityHierarchyPublicationContext $publicationContext = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method apply.
   *
   * Applies accepted patch fields to a published facility after checking ownership and facility constraints.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $resource the resource value
   * @param array<string, mixed> $patch the patch value
   *
   * @return void no return value
   */
  public function apply(string $organizationId, string $resource, array $patch): void
  {
    $this->assertPatchFields($patch);
    $record = $this->entityManager->find(FacilityRecord::class, $this->id($resource));
    if (!$record instanceof FacilityRecord || $record->organization?->id !== $organizationId || 'published' !== $record->recordStatus) {
      throw new FacilityPatchConflictException('Proposed facility change target is invalid.');
    }
    $previousStatus = $record->status;
    $this->assertHierarchyChangeAllowed($organizationId, $record, $patch);

    $this->applyTypeAndName($record, $patch);
    $this->applyFloorMetrics($record, $patch);
    $this->applyText($record, $patch);
    $this->applyCoordinates($record, $patch);
    $this->applyMetadata($organizationId, $record, $patch);
    $this->applyPlanGeometry($record, $patch);
    $this->applyStatus($record, $patch);
    $this->applyParent($organizationId, $record, $patch);

    // Restoring (archived -> active) is refused while the parent is archived,
    // mirroring the RestoreFacility use case and the canonical mutation processor.
    $this->assertStatusChangeAllowed($organizationId, $record, $previousStatus);

    if (null !== $record->planGeometry && array_key_exists('planGeometry', $patch)) {
      $this->assertPlanUsable($record);
    }

    $record->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method assertPlanUsable.
   *
   * Verifies the selected floor plan belongs to the facility or one of its ancestors.
   *
   * @access public
   *
   * @param FacilityRecord $record the facility with the proposed plan geometry
   *
   * @return void no return value
   *
   * @throws FacilityPatchConflictException when the attachment is invalid for the facility
   */
  public function assertPlanUsable(FacilityRecord $record): void
  {
    if (null === $record->planGeometry || null === $record->organization) {
      return;
    }

    try {
      $attachment = $this->attachments->findById(\Facility\Domain\ValueObject\FacilityAttachmentId::fromString($record->planGeometry['attachmentId']));
      if (null === $attachment || \Facility\Domain\ValueObject\AttachmentKind::FLOOR_PLAN !== $attachment->kind()) {
        throw new FacilityPatchConflictException('The proposed floor plan is unavailable.');
      }
      $facility = \Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityMapper::toDomain($record);
      $this->planAncestry->assertBelongsToFacilityOrAncestor($facility, $attachment, $facility->organizationId());
    } catch (\Facility\Domain\Exception\FacilityAttachmentNotAncestorException|InvalidValueException) {
      throw new FacilityPatchConflictException('The proposed floor plan does not belong to the facility ancestry.');
    }
  }

  /**
   * Method assertHierarchyChangeAllowed.
   *
   * Validates standalone relation mutations while the merged publication context owns grouped changes.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $organizationId the authorized organization
   * @param FacilityRecord $record the published target
   * @param array<string, mixed> $patch the explicit proposed fields
   *
   * @return void
   */
  private function assertHierarchyChangeAllowed(string $organizationId, FacilityRecord $record, array $patch): void
  {
    if (null !== $this->hierarchy && (array_key_exists('type', $patch) || array_key_exists('parent', $patch))
      && !$this->publicationContext?->covers($organizationId, $record->id)) {
      $type = $patch['type'] ?? $record->type;
      if (array_key_exists('parent', $patch) && null !== $patch['parent'] && !is_string($patch['parent'])) {
        throw new FacilityPatchConflictException('Proposed parent facility must be an IRI or null.');
      }
      $parentId = $this->proposedParentId($record, $patch);
      if (is_string($type)) {
        $this->hierarchy->lock($organizationId);
        $this->hierarchy->assertGraph($organizationId, [new \Facility\Application\Contract\Hierarchy\FacilityHierarchyNode($record->id, $type, $parentId, $record->status)]);
      }
    }
  }

  /**
   * Method proposedParentId.
   *
   * Distinguishes a retained parent from an explicitly cleared relation.
   *
   * @access private
   * @since 1.0.0
   *
   * @param FacilityRecord $record the current relationship
   * @param array<string, mixed> $patch the proposed parent when present
   *
   * @return ?string the effective parent identifier
   */
  private function proposedParentId(FacilityRecord $record, array $patch): ?string
  {
    if (!array_key_exists('parent', $patch)) {
      return $record->parentFacility?->id;
    }
    $parent = $patch['parent'];

    return is_string($parent) ? $this->id($parent) : null;
  }

  /**
   * @param array<string, mixed> $patch
   */
  private function applyTypeAndName(FacilityRecord $record, array $patch): void
  {
    if (array_key_exists('type', $patch)) {
      $type = $patch['type'];
      if (!is_string($type) || !in_array($type, self::TYPES, true)) {
        throw new FacilityPatchConflictException('Proposed facility type is invalid.');
      }
      $record->type = $type;
    }
    if (array_key_exists('name', $patch)) {
      $name = $patch['name'];
      if (!is_string($name) || '' === trim($name)) {
        throw new FacilityPatchConflictException('Facility name cannot be empty.');
      }
      $record->name = trim($name);
    }
  }

  /**
   * Method applyFloorMetrics
   *
   * Applies optional physical dimensions and clears them when a floor changes type.
   *
   * @access private
   *
   * @param FacilityRecord $record the proposed facility state
   * @param array<string, mixed> $patch present fields and their proposed values
   *
   * @return void
   *
   * @throws FacilityPatchConflictException when floor dimensions are invalid
   */
  private function applyFloorMetrics(FacilityRecord $record, array $patch): void
  {
    $type = FacilityType::from($record->type);
    $values = [];
    foreach (['elevationMeters', 'heightMeters'] as $field) {
      if (array_key_exists($field, $patch)) {
        $value = $patch[$field];
        if (null !== $value && !is_int($value) && !is_float($value)) {
          throw new FacilityPatchConflictException(sprintf('Facility field "%s" must be a number or null.', $field));
        }
        $values[$field] = null === $value ? null : (float) $value;
      } else {
        $values[$field] = FacilityType::FLOOR === $type ? $record->{$field} : null;
      }
    }

    try {
      FacilityFloorMetrics::assertValid($type, $values['elevationMeters'], $values['heightMeters']);
    } catch (InvalidValueException $exception) {
      throw new FacilityPatchConflictException($exception->getMessage(), previous: $exception);
    }

    $record->elevationMeters = $values['elevationMeters'];
    $record->heightMeters = $values['heightMeters'];
  }

  /**
   * @param array<string, mixed> $patch
   */
  private function applyText(FacilityRecord $record, array $patch): void
  {
    foreach (['code', 'address'] as $property) {
      if (array_key_exists($property, $patch)) {
        $value = $patch[$property];
        if (null !== $value && !is_string($value)) {
          throw new FacilityPatchConflictException(sprintf('Facility field "%s" must be a string or null.', $property));
        }
        $record->{$property} = is_string($value) ? trim($value) : null;
      }
    }
  }

  /**
   * @param array<string, mixed> $patch
   */
  private function applyCoordinates(FacilityRecord $record, array $patch): void
  {
    if (!array_key_exists('latitude', $patch) && !array_key_exists('longitude', $patch)) {
      return;
    }
    // Coordinates are pairwise, mirroring the canonical mutation processor.
    if (array_key_exists('latitude', $patch) !== array_key_exists('longitude', $patch)) {
      throw new FacilityPatchConflictException('Facility latitude and longitude must be provided together.');
    }
    $latitude = $patch['latitude'];
    $longitude = $patch['longitude'];
    if ((null === $latitude) !== (null === $longitude)) {
      throw new FacilityPatchConflictException('Facility latitude and longitude must be provided together.');
    }
    if (null === $latitude) {
      $record->latitude = null;
      $record->longitude = null;

      return;
    }

    [$latitude, $longitude] = self::coordinateValues($latitude, $longitude);
    $record->latitude = $latitude;
    $record->longitude = $longitude;
  }

  /**
   * @return array{float, float}
   */
  private static function coordinateValues(mixed $latitude, mixed $longitude): array
  {
    if (!is_int($latitude) && !is_float($latitude)) {
      throw new FacilityPatchConflictException('Facility latitude must be a number or null.');
    }
    if (!is_int($longitude) && !is_float($longitude)) {
      throw new FacilityPatchConflictException('Facility longitude must be a number or null.');
    }
    if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
      throw new FacilityPatchConflictException('Facility coordinates are out of range.');
    }

    return [(float) $latitude, (float) $longitude];
  }

  /**
   * @param array<string, mixed> $patch
   */
  private function applyMetadata(string $organizationId, FacilityRecord $record, array $patch): void
  {
    if (!array_key_exists('metadata', $patch)) {
      return;
    }
    if (!is_array($patch['metadata'])) {
      throw new FacilityPatchConflictException('Proposed facility metadata must be an object.');
    }
    /** @var array<string, mixed> $metadata */
    $metadata = $patch['metadata'];

    // Required is enforced on CREATE only, mirroring the canonical PATCH
    // surface and the command handlers.
    try {
      $this->metadataSchemaGuard->assertValid($organizationId, $metadata, $record->type, false);
    } catch (FacilityMetadataValidationException $exception) {
      throw new FacilityPatchConflictException($exception->getMessage());
    }
    $record->metadata = $metadata;
  }

  /**
   * @param array<string, mixed> $patch
   */
  private function applyPlanGeometry(FacilityRecord $record, array $patch): void
  {
    if (!array_key_exists('planGeometry', $patch)) {
      return;
    }
    $planGeometry = $patch['planGeometry'];
    if (null === $planGeometry) {
      $record->planGeometry = null;
    } elseif (is_array($planGeometry)) {
      try {
        /** @var array{attachmentId?: mixed, points?: mixed} $planGeometry */
        $record->planGeometry = PlanGeometry::fromArray($planGeometry)->toArray();
      } catch (InvalidValueException $exception) {
        throw new FacilityPatchConflictException($exception->getMessage());
      }
    } else {
      throw new FacilityPatchConflictException('Proposed facility plan geometry must be an object or null.');
    }
  }

  /**
   * @param array<string, mixed> $patch
   */
  private function applyStatus(FacilityRecord $record, array $patch): void
  {
    if (!array_key_exists('status', $patch)) {
      return;
    }
    $status = $patch['status'];
    if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
      throw new FacilityPatchConflictException('Proposed facility status is invalid.');
    }
    $record->status = $status;
  }

  /**
   * @param array<string, mixed> $patch
   */
  private function applyParent(string $organizationId, FacilityRecord $record, array $patch): void
  {
    if (!array_key_exists('parent', $patch)) {
      return;
    }
    $parentIri = $patch['parent'];
    if (null === $parentIri) {
      $record->parentFacility = null;
    } elseif (is_string($parentIri)) {
      $parent = $this->entityManager->find(FacilityRecord::class, $this->id($parentIri));
      if (!$parent instanceof FacilityRecord || $parent->organization?->id !== $organizationId) {
        throw new FacilityPatchConflictException('Proposed parent facility is invalid.');
      }
      if (!$this->publicationContext?->covers($organizationId, $record->id)) {
        $this->assertNoParentCycle($record, $parent);
      }
      if ('archived' === $parent->status && !$this->publicationContext?->covers($organizationId, $record->id)) {
        throw new FacilityPatchConflictException('Proposed parent facility is archived.');
      }
      if (!$this->publicationContext?->covers($organizationId, $record->id)) {
        $this->assertDepthWithinCap($record, $parent);
      }
      $record->parentFacility = $parent;
    } else {
      throw new FacilityPatchConflictException('Proposed parent facility must be an IRI or null.');
    }
  }

  /**
   * Method assertStatusChangeAllowed.
   *
   * Prevents invalid restore and archive transitions involving hierarchy dependents.
   *
   * @access private
   *
   * @param string $organizationId owning organization identifier
   * @param FacilityRecord $record facility after applying the patch
   * @param string $previousStatus status before the patch
   *
   * @return void no return value
   *
   * @throws FacilityPatchConflictException when the requested status change violates hierarchy rules
   */
  private function assertStatusChangeAllowed(string $organizationId, FacilityRecord $record, string $previousStatus): void
  {
    // Restoring (archived -> active) is refused while the parent is archived.
    if (
      'archived' === $previousStatus
      && 'active' === $record->status
      && $record->parentFacility instanceof FacilityRecord
      && 'archived' === $record->parentFacility->status
      && !$this->publicationContext?->covers($organizationId, $record->id)
    ) {
      throw new FacilityPatchConflictException('Cannot restore a facility while its parent is archived.');
    }

    // Archiving must not orphan a live dependent, mirroring the canonical surface.
    if ('archived' !== $previousStatus && 'archived' === $record->status && !$this->publicationContext?->covers($organizationId, $record->id)) {
      try {
        $this->archivalGuard->assertNoActiveDependents($organizationId, $record->id);
      } catch (FacilityHasActiveDependentsException $exception) {
        throw new FacilityPatchConflictException($exception->getMessage());
      }
    }
  }

  /**
   * Method assertNoParentCycle.
   *
   * Rejects a parent assignment that would create a hierarchy cycle by walking
   * the proposed parent's ancestry back to the record being reparented.
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the facility being reparented
   * @param FacilityRecord $parent the proposed parent
   */
  private function assertNoParentCycle(FacilityRecord $record, FacilityRecord $parent): void
  {
    $ancestor = $parent;
    while ($ancestor instanceof FacilityRecord) {
      if ($ancestor->id === $record->id) {
        throw new FacilityPatchConflictException('Proposed parent facility would create a hierarchy cycle.');
      }
      $ancestor = $ancestor->parentFacility;
    }
  }

  /**
   * Method assertDepthWithinCap.
   *
   * Refuses a proposed re-parenting that would push the record (and whatever
   * PUBLISHED sub-tree still hangs beneath it) past the configured hierarchy
   * depth cap. Depth and height are computed over the PUBLISHED tree only,
   * matching the scope of the surrounding `apply()` guard (published records
   * only).
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the facility being reparented
   * @param FacilityRecord $parent the proposed parent
   */
  private function assertDepthWithinCap(FacilityRecord $record, FacilityRecord $parent): void
  {
    $prospectiveDepth = $this->facilityRepository->depthOf(FacilityId::fromString($parent->id))
      + 1
      + $this->facilityRepository->subtreeHeight(FacilityId::fromString($record->id));

    if ($prospectiveDepth > $this->maxDepth) {
      throw new FacilityPatchConflictException(FacilityHierarchyException::maxDepthExceeded($this->maxDepth)->getMessage());
    }
  }

  /**
   * Method id.
   *
   * Extracts the facility identifier from its canonical API resource IRI and rejects malformed values.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $resource the resource value
   *
   * @return string the id result
   */
  private function id(string $resource): string
  {
    if (1 !== preg_match('#^/api/facilities/([^/]+)$#', $resource, $matches)) {
      throw new FacilityPatchConflictException('Invalid facility resource IRI.');
    }

    return $matches[1];
  }

  /**
   * Method assertPatchFields.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $patch
   */
  private function assertPatchFields(array $patch): void
  {
    $unknown = array_diff(array_keys($patch), self::PATCHABLE_FIELDS);
    if ([] !== $unknown) {
      throw new FacilityPatchConflictException(sprintf('Unsupported facility patch fields: %s.', implode(', ', $unknown)));
    }
  }
  // #endregion
}
