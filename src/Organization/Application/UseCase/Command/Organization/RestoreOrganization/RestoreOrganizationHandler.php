<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RestoreOrganization;

use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Event\Organization\OrganizationRestoredEvent;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationStatus};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase RestoreOrganizationHandler.
 *
 * Restores an organization to ACTIVE as an explicit, dedicated action, from
 * either SUSPENDED or ARCHIVED — {@see \Organization\Domain\Model\Organization\Organization::activate()}
 * carries no state guard, mirroring how
 * {@see \Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\UpdateOrganizationSettingsHandler}
 * already allows `isActive: true` to un-archive. Today the settings path
 * remains the only other way to reach this transition (see MODULE.md).
 *
 * Idempotent when the organization is already active, mirroring
 * {@see \Organization\Application\UseCase\Command\Organization\DeleteOrganization\DeleteOrganizationHandler}'s
 * idempotence: the second call neither mutates nor re-dispatches the event.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoreOrganizationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RestoreOrganizationHandler class.
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
   * @param RestoreOrganizationCommand $command the command payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   *
   * @return RestoreOrganizationResult the use case result
   */
  public function __invoke(RestoreOrganizationCommand $command): RestoreOrganizationResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $previousStatus = $organization->status();

    if (OrganizationStatus::ACTIVE !== $previousStatus) {
      $organization->activate();
      $this->organizationRepository->save($organization);

      $this->eventDispatcher->dispatch(new OrganizationRestoredEvent(
        organizationId: (string) $organizationId,
        previousStatus: $previousStatus->value,
      ));
    }

    return new RestoreOrganizationResult(
      organizationId: (string) $organizationId,
      status: $organization->status()->value,
      updatedAt: $organization->updatedAt(),
    );
  }
  // #endregion
}
