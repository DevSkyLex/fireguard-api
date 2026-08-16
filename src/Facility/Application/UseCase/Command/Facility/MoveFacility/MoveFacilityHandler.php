<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\MoveFacility;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Event\Facility\FacilityMovedEvent;
use Facility\Domain\Exception\{
  FacilityArchivedException,
  FacilityHierarchyException,
  FacilityNotFoundException
};
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function array_key_exists;
use function str_contains;
use function strtolower;

/**
 * UseCase MoveFacilityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MoveFacilityHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private EventDispatcherPort $eventDispatcher,
    #[Autowire('%facility.hierarchy.max_depth%')]
    private int $maxDepth = 8,
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
   * @param MoveFacilityCommand $command the command payload
   *
   * @return MoveFacilityResult the use case result
   */
  public function __invoke(MoveFacilityCommand $command): MoveFacilityResult
  {
    try {
      $facilityId = FacilityId::fromString($command->facilityId);
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
      $parentId = $this->resolveParentId($command->parentFacilityId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    // Published-only lookup: draft intervention scratchpads fall through to
    // the not-found path and can neither be moved nor audited.
    $facility = $this->facilityRepository->findPublishedById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    if (null !== $parentId && $facility->id()->equals($parentId)) {
      throw FacilityHierarchyException::cannotUseSelfAsParent();
    }

    if (null !== $parentId) {
      $this->assertParentHierarchyIsValid($facilityId, $organizationId, $parentId);
    }

    // Captured before the mutation so the audit event carries the real origin.
    $previousParentFacilityId = $facility->parentFacilityId()?->__toString();

    $facility->moveTo($parentId);

    try {
      $this->facilityRepository->save($facility);
    } catch (Throwable $exception) {
      if ($this->isOrganizationConstraintViolation($exception)) {
        throw new InvalidArgumentException('Organization not found.');
      }

      if ($this->isParentConstraintViolation($exception)) {
        throw FacilityNotFoundException::withId((string) ($parentId ?? 'unknown'));
      }

      throw $exception;
    }

    // Emitted after the durable save so a failed persistence leaves no ledger
    // row; a same-parent move is a no-op and must not emit.
    $newParentFacilityId = $facility->parentFacilityId()?->__toString();
    if ($previousParentFacilityId !== $newParentFacilityId) {
      $this->eventDispatcher->dispatch(new FacilityMovedEvent(
        organizationId: (string) $facility->organizationId(),
        facilityId: (string) $facility->id(),
        previousParentFacilityId: $previousParentFacilityId,
        newParentFacilityId: $newParentFacilityId,
      ));
    }

    return new MoveFacilityResult(
      facilityId: (string) $facility->id(),
      organizationId: (string) $facility->organizationId(),
      parentFacilityId: $facility->parentFacilityId()?->__toString(),
      type: $facility->type()->value,
      name: (string) $facility->name(),
      code: $facility->code(),
      status: $facility->status()->value,
      address: $facility->address(),
      metadata: $facility->metadata(),
      createdAt: $facility->createdAt(),
      updatedAt: $facility->updatedAt(),
    );
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
   * Method assertParentHierarchyIsValid.
   *
   * @since 1.0.0
   *
   * @param FacilityId $facilityId the facility being moved
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityId $parentId the target parent identifier
   */
  private function assertParentHierarchyIsValid(
    FacilityId $facilityId,
    FacilityOrganizationId $organizationId,
    FacilityId $parentId,
  ): void {
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

    // Moving a subtree deeper must account for its own height: every
    // descendant shifts by the same amount as the moved root.
    $prospectiveDepth = $this->facilityRepository->depthOf($parentId) + 1 + $this->facilityRepository->subtreeHeight($facilityId);
    if ($prospectiveDepth > $this->maxDepth) {
      throw FacilityHierarchyException::maxDepthExceeded($this->maxDepth);
    }

    $current = $parent;
    $visited = [];

    while (null !== $current->parentFacilityId()) {
      $currentId = (string) $current->id();
      if (array_key_exists($currentId, $visited)) {
        throw FacilityHierarchyException::hierarchyCycleDetected();
      }

      $visited[$currentId] = true;
      $ancestorId = $current->parentFacilityId();

      if ($facilityId->equals($ancestorId) || array_key_exists((string) $ancestorId, $visited)) {
        throw FacilityHierarchyException::hierarchyCycleDetected();
      }

      $ancestor = $this->facilityRepository->findById($ancestorId);
      if (null === $ancestor) {
        break;
      }

      if ((string) $ancestor->organizationId() !== (string) $organizationId) {
        throw FacilityHierarchyException::parentInAnotherOrganization();
      }

      $current = $ancestor;
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
