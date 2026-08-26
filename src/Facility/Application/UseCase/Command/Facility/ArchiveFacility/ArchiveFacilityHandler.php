<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\ArchiveFacility;

use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Event\Facility\FacilityArchivedEvent;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use Throwable;

use function sprintf;

/**
 * UseCase ArchiveFacilityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveFacilityHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private OrganizationRepositoryPort $organizationRepository,
    private NotificationPort $notificationPort,
    private LoggerPort $logger,
    private FacilityArchivalGuardPort $archivalGuard,
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
   * @param ArchiveFacilityCommand $command the command payload
   *
   * @return ArchiveFacilityResult the use case result
   */
  public function __invoke(ArchiveFacilityCommand $command): ArchiveFacilityResult
  {
    $facilityId = FacilityId::fromString($command->facilityId);
    $organizationId = FacilityOrganizationId::fromString($command->organizationId);

    $facility = $this->facilityRepository->findPublishedById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    $wasAlreadyArchived = 'archived' === $facility->status()->value;

    // Refuse to archive a facility that would orphan a live dependent (active
    // child facilities, equipment, or in-progress inspections). Idempotent: an
    // already-archived facility has no live dependents to re-check.
    if (!$wasAlreadyArchived) {
      $this->archivalGuard->assertNoActiveDependents((string) $organizationId, (string) $facilityId);
    }

    $facility->archive();

    // The repository translates a missing-organization foreign key into
    // FacilityOrganizationNotFoundException; Presentation maps it like any
    // other InvalidArgumentException.
    $this->facilityRepository->save($facility);

    if (!$wasAlreadyArchived) {
      // Emitted after the durable save so a failed persistence leaves no
      // ledger row; the idempotent already-archived path stays silent.
      $this->eventDispatcher->dispatch(new FacilityArchivedEvent(
        organizationId: (string) $facility->organizationId(),
        facilityId: (string) $facility->id(),
      ));

      $organization = $this->organizationRepository->findById(new OrganizationId((string) $organizationId));
    } else {
      $organization = null;
    }

    if (null !== $organization) {
      try {
        $this->notificationPort->send(new SendNotificationRequest(
          type: NotificationType::FACILITY_ARCHIVED,
          subject: 'Facility archived',
          body: sprintf('Facility %s has been archived.', (string) $facility->name()),
          channels: [NotificationChannel::MERCURE],
          payload: [
            'organizationId' => (string) $organizationId,
            'facilityId' => (string) $facility->id(),
            'facilityName' => (string) $facility->name(),
            'facilityType' => $facility->type()->value,
            'archivedAt' => $facility->updatedAt()->format('c'),
          ],
          recipientUserId: $organization->ownerUserId(),
          organizationId: (string) $organizationId,
        ));
      } catch (Throwable $exception) {
        $this->logger->warning('Facility archived notification dispatch failed.', [
          'organizationId' => (string) $organizationId,
          'facilityId' => (string) $facility->id(),
          'recipientUserId' => $organization->ownerUserId(),
          'error' => $exception->getMessage(),
        ]);
      }
    }

    return new ArchiveFacilityResult(
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

  // #endregion
}
