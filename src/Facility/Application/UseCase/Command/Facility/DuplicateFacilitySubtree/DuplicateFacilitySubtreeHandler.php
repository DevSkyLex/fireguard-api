<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Event\Facility\FacilitySubtreeDuplicatedEvent;
use Facility\Domain\Exception\{
  FacilityArchivedException,
  FacilityHierarchyException,
  FacilityNotFoundException,
  FacilityOrganizationNotFoundException,
  FacilitySubtreeSourceArchivedException,
  FacilitySubtreeTooLargeException
};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId};
use Organization\Application\Contract\Quota\OrganizationQuotaResource;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Throwable;

use function count;
use function str_contains;
use function strtolower;

/**
 * UseCase DuplicateFacilitySubtreeHandler.
 *
 * Clones a published, active facility and its full published subtree
 * (fetched through the existing descendants recursive CTE) into a new,
 * independent branch of the location hierarchy. See `src/Facility/MODULE.md`
 * for the cloning rules this handler enforces.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DuplicateFacilitySubtreeHandler implements CommandHandler
{
  /**
   * Defensive cap on the number of nodes (source included) a duplication may
   * traverse. Refused with FacilitySubtreeTooLargeException beyond this.
   *
   * @since 1.0.0
   */
  private const int MAX_SUBTREE_NODES = 500;

  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private UuidFactory $uuidFactory,
    private OrganizationQuotaPort $quota,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param DuplicateFacilitySubtreeCommand $command the command payload
   *
   * @return DuplicateFacilitySubtreeResult the use case result
   */
  public function __invoke(DuplicateFacilitySubtreeCommand $command): DuplicateFacilitySubtreeResult
  {
    $organizationId = FacilityOrganizationId::fromString($command->organizationId);
    $sourceId = FacilityId::fromString($command->facilityId);
    $requestedParentId = $this->resolveParentId($command->parentFacilityId);

    // Published-only lookup: draft intervention scratchpads fall through to
    // the not-found path and can neither be duplicated nor audited.
    $source = $this->facilityRepository->findPublishedById($sourceId);

    if (null === $source || (string) $source->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    // Duplicating an archived branch must not implicitly un-archive the
    // copies: refuse outright rather than silently reactivating the lineage.
    if (!$source->status()->isActive()) {
      throw FacilitySubtreeSourceArchivedException::withId($command->facilityId);
    }

    $targetParentId = $requestedParentId ?? $source->parentFacilityId();
    if (null !== $targetParentId) {
      $this->assertParentIsUsable($targetParentId, $organizationId);
    }

    // includeArchived: true — an archived intermediate node must still be
    // traversed so a live descendant beneath it is reached, mirroring the
    // archival guard's own traversal (see MODULE.md).
    $descendants = $this->facilityRepository->findDescendants(
      organizationId: $organizationId,
      facilityId: $sourceId,
      includeArchived: true,
    );

    $totalTraversed = 1 + count($descendants);
    if ($totalTraversed > self::MAX_SUBTREE_NODES) {
      throw FacilitySubtreeTooLargeException::exceedsLimit($command->facilityId, $totalTraversed, self::MAX_SUBTREE_NODES);
    }

    /** @var array<string, list<Facility>> $childrenByParent */
    $childrenByParent = [];
    foreach ($descendants as $descendant) {
      $parentKey = $descendant->parentFacilityId()?->__toString() ?? '';
      $childrenByParent[$parentKey][] = $descendant;
    }

    $rootName = null !== $command->name
      ? new FacilityName($command->name)
      : new FacilityName(((string) $source->name()) . ' (copy)');

    $newRoot = $this->cloneNode($source, $this->uuidFactory->create(FacilityId::class), $targetParentId, $rootName);

    /** @var list<Facility> $clones */
    $clones = [$newRoot];
    $this->appendClonedDescendants($sourceId, $newRoot, $childrenByParent, $clones);

    $nodeCount = count($clones);

    // Enforce the plan quota and persist every clone in one transaction:
    // assertCanAddMultiple takes a transaction-scoped advisory lock so no
    // concurrent create/duplicate can slip past the count between the check
    // and the inserts (see OrganizationQuotaPort::assertCanAddMultiple).
    $this->transactionManager->transactional(function () use ($organizationId, $clones, $nodeCount): void {
      $this->quota->assertCanAddMultiple((string) $organizationId, OrganizationQuotaResource::FACILITIES, $nodeCount);

      foreach ($clones as $clone) {
        try {
          $this->facilityRepository->save($clone);
        } catch (Throwable $exception) {
          if ($this->isOrganizationConstraintViolation($exception)) {
            throw FacilityOrganizationNotFoundException::create();
          }

          if ($this->isParentConstraintViolation($exception)) {
            throw FacilityNotFoundException::withId((string) ($clone->parentFacilityId() ?? 'unknown'));
          }

          throw $exception;
        }
      }
    });

    // Emitted once, after the durable save, so a failed persistence leaves
    // no ledger row.
    $this->eventDispatcher->dispatch(new FacilitySubtreeDuplicatedEvent(
      organizationId: (string) $organizationId,
      sourceFacilityId: (string) $sourceId,
      newRootFacilityId: (string) $newRoot->id(),
      nodeCount: $nodeCount,
    ));

    return new DuplicateFacilitySubtreeResult(
      facilityId: (string) $newRoot->id(),
      organizationId: (string) $newRoot->organizationId(),
      parentFacilityId: $newRoot->parentFacilityId()?->__toString(),
      type: $newRoot->type()->value,
      name: (string) $newRoot->name(),
      code: $newRoot->code(),
      status: $newRoot->status()->value,
      address: $newRoot->address(),
      metadata: $newRoot->metadata(),
      createdAt: $newRoot->createdAt(),
      updatedAt: $newRoot->updatedAt(),
      latitude: $newRoot->coordinates()?->latitude(),
      longitude: $newRoot->coordinates()?->longitude(),
      nodeCount: $nodeCount,
    );
  }

  /**
   * Method cloneNode.
   *
   * Builds one clone: type, address, metadata, and coordinates are copied;
   * code is always null (uniq_facility_organization_code makes copying the
   * original code impossible); status is always active; clientId and
   * interventionId are left unset by the repository so the clone is a fresh
   * published record at revision 1.
   *
   * @since 1.0.0
   *
   * @param Facility $original the node being cloned
   * @param FacilityId $newId the clone's identifier
   * @param ?FacilityId $newParentId the clone's parent identifier
   * @param FacilityName $name the clone's name
   *
   * @return Facility the new facility aggregate, not yet persisted
   */
  private function cloneNode(Facility $original, FacilityId $newId, ?FacilityId $newParentId, FacilityName $name): Facility
  {
    return Facility::create(
      id: $newId,
      organizationId: $original->organizationId(),
      type: $original->type(),
      name: $name,
      parentFacilityId: $newParentId,
      code: null,
      address: $original->address(),
      metadata: $original->metadata(),
      coordinates: $original->coordinates(),
    );
  }

  /**
   * Method appendClonedDescendants.
   *
   * Walks the original subtree top-down, cloning each active descendant
   * under its cloned parent. An archived descendant is skipped — no clone is
   * created for it — but its own live children are still visited and
   * reattached to the nearest cloned ancestor, so a live branch beneath an
   * archived one is not silently dropped.
   *
   * @since 1.0.0
   *
   * @param FacilityId $originalParentId the original node whose children are being visited
   * @param Facility $nearestClonedAncestor the clone new children should be reattached to when their own parent was skipped
   * @param array<string, list<Facility>> $childrenByParent original children indexed by original parent id
   * @param list<Facility> $clones accumulator of clones to persist, appended in top-down order
   */
  private function appendClonedDescendants(
    FacilityId $originalParentId,
    Facility $nearestClonedAncestor,
    array $childrenByParent,
    array &$clones,
  ): void {
    $children = $childrenByParent[(string) $originalParentId] ?? [];

    foreach ($children as $child) {
      if (!$child->status()->isActive()) {
        $this->appendClonedDescendants($child->id(), $nearestClonedAncestor, $childrenByParent, $clones);

        continue;
      }

      $childClone = $this->cloneNode($child, $this->uuidFactory->create(FacilityId::class), $nearestClonedAncestor->id(), $child->name());
      $clones[] = $childClone;
      $this->appendClonedDescendants($child->id(), $childClone, $childrenByParent, $clones);
    }
  }

  /**
   * Method resolveParentId.
   *
   * @since 1.0.0
   *
   * @param ?string $parentFacilityId the optional parent identifier
   *
   * @return ?FacilityId the normalized parent identifier
   */
  private function resolveParentId(?string $parentFacilityId): ?FacilityId
  {
    if (null === $parentFacilityId) {
      return null;
    }

    return FacilityId::fromString($parentFacilityId);
  }

  /**
   * Method assertParentIsUsable.
   *
   * @since 1.0.0
   *
   * @param FacilityId $parentId the target parent identifier
   * @param FacilityOrganizationId $organizationId the organization identifier
   */
  private function assertParentIsUsable(FacilityId $parentId, FacilityOrganizationId $organizationId): void
  {
    $parent = $this->facilityRepository->findById($parentId);
    if (null === $parent) {
      throw FacilityNotFoundException::withId((string) $parentId);
    }

    if ((string) $parent->organizationId() !== (string) $organizationId) {
      throw FacilityHierarchyException::parentInAnotherOrganization();
    }

    if (!$parent->status()->isActive()) {
      throw FacilityArchivedException::withId((string) $parentId);
    }
  }

  /**
   * Method isOrganizationConstraintViolation.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the transactional exception
   *
   * @return bool true when the failure is caused by organization FK
   */
  private function isOrganizationConstraintViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof ForeignKeyConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'fk_facility_organization') || (str_contains($message, 'facilities') && str_contains($message, 'organization'))) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }

  /**
   * Method isParentConstraintViolation.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the transactional exception
   *
   * @return bool true when the failure is caused by parent FK
   */
  private function isParentConstraintViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof ForeignKeyConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'fk_facility_parent') || (str_contains($message, 'facilities') && str_contains($message, 'parent'))) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }
  // #endregion
}
