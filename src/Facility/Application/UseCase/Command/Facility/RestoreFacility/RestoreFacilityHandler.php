<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\RestoreFacility;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Event\Facility\FacilityRestoredEvent;
use Facility\Domain\Exception\{FacilityArchivedException, FacilityNotFoundException, FacilityOrganizationNotFoundException};
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Throwable;

use function str_contains;
use function strtolower;

/**
 * Class RestoreFacilityHandler
 *
 * Restores a facility within its organization and emits an event only after a persisted state transition.
 *
 * @category UseCase
 */
final readonly class RestoreFacilityHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies facility persistence and post-save event dispatch.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository facility lookup and persistence
   * @param EventDispatcherPort $eventDispatcher dispatcher for the committed restoration event
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Restores an organization-scoped facility after validating its parent and translates persistence constraints.
   *
   * @access public
   *
   * @param RestoreFacilityCommand $command facility and organization identifiers
   *
   * @return RestoreFacilityResult restored facility snapshot
   *
   * @throws FacilityNotFoundException when the facility or parent is outside the organization or missing
   * @throws FacilityArchivedException when its parent facility is inactive
   * @throws FacilityOrganizationNotFoundException when the organization foreign key no longer exists
   */
  public function __invoke(RestoreFacilityCommand $command): RestoreFacilityResult
  {
    $facilityId = FacilityId::fromString($command->facilityId);
    $organizationId = FacilityOrganizationId::fromString($command->organizationId);

    $facility = $this->facilityRepository->findPublishedById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    // Captured BEFORE the mutation: the restored event is only emitted when
    // the facility actually transitions back to active (idempotent repeats
    // on an already-active facility stay silent).
    $wasArchived = !$facility->status()->isActive();

    $parentId = $facility->parentFacilityId();
    if (null !== $parentId) {
      $parent = $this->facilityRepository->findById($parentId);

      if (null === $parent || (string) $parent->organizationId() !== (string) $organizationId) {
        throw FacilityNotFoundException::withId((string) $parentId);
      }

      if (!$parent->status()->isActive()) {
        throw FacilityArchivedException::withId((string) $parentId);
      }
    }

    $facility->restore();

    try {
      $this->facilityRepository->save($facility);
    } catch (Throwable $exception) {
      if ($this->isOrganizationConstraintViolation($exception)) {
        throw FacilityOrganizationNotFoundException::create();
      }

      throw $exception;
    }

    // Post-commit: the repository save above flushed the transition, so the
    // audit event can no longer be rolled back from under its consumers.
    if ($wasArchived) {
      $this->eventDispatcher->dispatch(new FacilityRestoredEvent(
        organizationId: (string) $organizationId,
        facilityId: (string) $facility->id(),
      ));
    }

    return new RestoreFacilityResult(
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
   * Method isOrganizationConstraintViolation
   *
   * Checks the exception chain for a facility-to-organization foreign-key failure.
   *
   * @access private
   *
   * @param Throwable $exception persistence exception to inspect
   *
   * @return bool whether the chain contains the organization constraint violation
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
  // #endregion
}
