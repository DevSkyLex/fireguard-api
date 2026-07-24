<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganization;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Event\Organization\OrganizationArchivedEvent;
use Organization\Domain\Exception\{OrganizationDeletionConfirmationMismatchException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function strtolower;
use function trim;

/**
 * UseCase DeleteOrganizationHandler.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteOrganizationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeleteOrganizationCommand $command the command payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationDeletionConfirmationMismatchException when the slug
   *                                                           confirmation is
   *                                                           missing or does
   *                                                           not match the
   *                                                           organization's
   *                                                           current slug
   *
   * @return DeleteOrganizationResult the use case result
   */
  public function __invoke(DeleteOrganizationCommand $command): DeleteOrganizationResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    // Danger-zone guard: the caller must type the organization's current slug
    // to confirm intent. Checked on every call (including retries against an
    // already-archived organization) so a wrong/missing confirmation is never
    // a silent no-op. Comparison is normalized (trim + lowercase) the same
    // way OrganizationSlug itself normalizes, without constructing the value
    // object (arbitrary typed text must never throw a VO validation error —
    // it is simply a mismatch).
    $expectedSlug = (string) $organization->slug();
    $providedSlug = null !== $command->slugConfirmation ? strtolower(trim($command->slugConfirmation)) : '';

    if ('' === $providedSlug) {
      throw OrganizationDeletionConfirmationMismatchException::missing();
    }

    if ($providedSlug !== $expectedSlug) {
      throw OrganizationDeletionConfirmationMismatchException::mismatched();
    }

    // Soft delete: archive the organization instead of hard-deleting the record,
    // so its facilities/equipment/inspections/interventions are never orphaned
    // and the organization can be restored. Idempotent when already archived —
    // note this only guards the archive transition; it is NOT a hard/permanent
    // delete (see MODULE.md).
    if (!$organization->isArchived()) {
      $organization->archive();
      $this->organizationRepository->save($organization);

      $this->eventDispatcher->dispatch(new OrganizationArchivedEvent(
        organizationId: (string) $organizationId,
      ));
    }

    return new DeleteOrganizationResult(
      organizationId: (string) $organizationId,
      deletedAt: new DateTimeImmutable(),
    );
  }
  // #endregion
}
