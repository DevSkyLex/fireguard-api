<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\SuspendOrganization;

use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Event\Organization\OrganizationSuspendedEvent;
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationStatus};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase SuspendOrganizationHandler.
 *
 * Suspends an organization as an explicit, dedicated action. Today the only
 * other path to SUSPENDED is `isActive: false` on
 * {@see \Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\UpdateOrganizationSettingsHandler},
 * which this use case does not replace — both remain available (see
 * MODULE.md).
 *
 * Idempotent when the organization is already suspended, mirroring
 * {@see \Organization\Application\UseCase\Command\Organization\DeleteOrganization\DeleteOrganizationHandler}'s
 * idempotence on an already-archived organization: the second call neither
 * mutates nor re-dispatches the event.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SuspendOrganizationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SuspendOrganizationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher port
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
   * @param SuspendOrganizationCommand $command the command payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationArchivedException when the organization is archived
   *
   * @return SuspendOrganizationResult the use case result
   */
  public function __invoke(SuspendOrganizationCommand $command): SuspendOrganizationResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    if (OrganizationStatus::ARCHIVED === $organization->status()) {
      throw OrganizationArchivedException::cannotSuspend();
    }

    if (OrganizationStatus::SUSPENDED !== $organization->status()) {
      $organization->deactivate();
      $this->organizationRepository->save($organization);

      $this->eventDispatcher->dispatch(new OrganizationSuspendedEvent(
        organizationId: (string) $organizationId,
      ));
    }

    return new SuspendOrganizationResult(
      organizationId: (string) $organizationId,
      status: $organization->status()->value,
      updatedAt: $organization->updatedAt(),
    );
  }
  // #endregion
}
