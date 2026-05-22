<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\ArchiveFacility;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function sprintf;
use function str_contains;
use function strtolower;

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
    try {
      $facilityId = FacilityId::fromString($command->facilityId);
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    $wasAlreadyArchived = 'archived' === $facility->status()->value;

    $facility->archive();

    try {
      $this->facilityRepository->save($facility);
    } catch (Throwable $exception) {
      if ($this->isOrganizationConstraintViolation($exception)) {
        throw new InvalidArgumentException('Organization not found.');
      }

      throw $exception;
    }

    if (!$wasAlreadyArchived) {
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
  // #endregion
}
