<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\UpdateFacility;

use Doctrine\DBAL\Exception\{
  ForeignKeyConstraintViolationException,
  UniqueConstraintViolationException
};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Domain\Event\Facility\FacilityUpdatedEvent;
use Facility\Domain\Exception\{
  FacilityCodeAlreadyExistsException,
  FacilityNotFoundException,
  FacilityOrganizationNotFoundException
};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{
  FacilityCoordinates,
  FacilityId,
  FacilityName,
  FacilityOrganizationId,
  FacilityType
};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use ValueError;

use function str_contains;
use function strtolower;

/**
 * UseCase UpdateFacilityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateFacilityHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FacilityRepositoryPort $facilityRepository the facility repository port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private EventDispatcherPort $eventDispatcher,
    private FacilityMetadataSchemaGuard $metadataSchemaGuard,
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
   * @param UpdateFacilityCommand $command the command payload
   *
   * @return UpdateFacilityResult the use case result
   */
  public function __invoke(UpdateFacilityCommand $command): UpdateFacilityResult
  {
    try {
      $facilityId = FacilityId::fromString($command->facilityId);
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException|ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    // Captured before the mutation so the changed-field list reflects what
    // actually differs, not merely which fields were present in the patch —
    // a PATCH that re-sends the current value must stay a no-op.
    $previousType = $facility->type();
    $previousName = (string) $facility->name();
    $previousCode = $facility->code();
    $previousAddress = $facility->address();
    $previousCoordinates = $facility->coordinates();
    $previousMetadata = $facility->metadata();

    try {
      if ($command->hasType) {
        if (null === $command->type) {
          throw InvalidValueException::because('Field "type" cannot be null when provided.');
        }

        $facility->changeType(FacilityType::from($command->type));
      }

      if ($command->hasName) {
        if (null === $command->name) {
          throw InvalidValueException::because('Field "name" cannot be null when provided.');
        }

        $facility->rename(new FacilityName($command->name));
      }

      if ($command->hasCode) {
        $facility->changeCode($command->code);
      }

      if ($command->hasAddress) {
        $facility->changeAddress($command->address);
      }

      if ($command->hasLatitude || $command->hasLongitude) {
        $facility->changeCoordinates($this->resolveCoordinates($command));
      }

      if ($command->hasMetadata) {
        $facility->changeMetadata($command->metadata ?? []);
      }
    } catch (InvalidValueException|ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    if ($command->hasMetadata) {
      // `required` is enforced on CREATE only — a partial PATCH is never
      // rejected for a required key it never touched.
      $this->metadataSchemaGuard->assertValid(
        $command->organizationId,
        $facility->metadata(),
        $facility->type()->value,
        false,
      );
    }

    try {
      $this->facilityRepository->save($facility);
    } catch (Throwable $exception) {
      if ($this->isDuplicateCodeConstraintViolation($exception)) {
        throw FacilityCodeAlreadyExistsException::withCode($facility->code() ?? 'unknown');
      }

      if ($this->isOrganizationConstraintViolation($exception)) {
        throw FacilityOrganizationNotFoundException::create();
      }

      throw $exception;
    }

    // Emitted after the durable save so a failed persistence leaves no
    // ledger row; a patch that changes nothing (same values re-sent) must
    // not emit. Status and parent are covered by the dedicated
    // archived/restored/moved events and are never listed here.
    $changedFields = $this->changedFields(
      $facility,
      $previousType,
      $previousName,
      $previousCode,
      $previousAddress,
      $previousCoordinates,
      $previousMetadata,
    );
    if ([] !== $changedFields) {
      $this->eventDispatcher->dispatch(new FacilityUpdatedEvent(
        organizationId: (string) $facility->organizationId(),
        facilityId: (string) $facility->id(),
        changedFields: $changedFields,
      ));
    }

    return new UpdateFacilityResult(
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
      latitude: $facility->coordinates()?->latitude(),
      longitude: $facility->coordinates()?->longitude(),
    );
  }

  /**
   * Method resolveCoordinates.
   *
   * Builds the facility coordinates value object from the partial update
   * command. Latitude and longitude must both be provided together (to set
   * or replace coordinates), or both omitted from the payload (to clear
   * coordinates), when either is present.
   *
   * @since 1.0.0
   *
   * @param UpdateFacilityCommand $command the command payload
   *
   * @return ?FacilityCoordinates the resolved coordinates, or null to clear them
   */
  private function resolveCoordinates(UpdateFacilityCommand $command): ?FacilityCoordinates
  {
    if ($command->hasLatitude !== $command->hasLongitude) {
      throw InvalidValueException::because('Facility latitude and longitude must be provided together.');
    }

    if (null === $command->latitude && null === $command->longitude) {
      return null;
    }

    if (null === $command->latitude || null === $command->longitude) {
      throw InvalidValueException::because('Facility latitude and longitude must be provided together.');
    }

    return new FacilityCoordinates($command->latitude, $command->longitude);
  }

  /**
   * Method changedFields.
   *
   * Compares the facility's post-mutation state against the snapshot taken
   * before the patch was applied and returns the names of the fields that
   * actually differ. `status` and `parent` are deliberately excluded: they
   * are covered by the dedicated archive/restore/move events.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the facility after mutation
   * @param FacilityType $previousType the type before mutation
   * @param string $previousName the name before mutation
   * @param ?string $previousCode the code before mutation
   * @param ?string $previousAddress the address before mutation
   * @param ?FacilityCoordinates $previousCoordinates the coordinates before mutation
   * @param array<string, mixed> $previousMetadata the metadata before mutation
   *
   * @return list<string> the changed field names
   */
  private function changedFields(
    Facility $facility,
    FacilityType $previousType,
    string $previousName,
    ?string $previousCode,
    ?string $previousAddress,
    ?FacilityCoordinates $previousCoordinates,
    array $previousMetadata,
  ): array {
    $changed = [];

    if ($previousType !== $facility->type()) {
      $changed[] = 'type';
    }

    if ($previousName !== (string) $facility->name()) {
      $changed[] = 'name';
    }

    if ($previousCode !== $facility->code()) {
      $changed[] = 'code';
    }

    if ($previousAddress !== $facility->address()) {
      $changed[] = 'address';
    }

    if (!$this->coordinatesEqual($previousCoordinates, $facility->coordinates())) {
      $changed[] = 'coordinates';
    }

    if ($previousMetadata !== $facility->metadata()) {
      $changed[] = 'metadata';
    }

    return $changed;
  }

  /**
   * Method coordinatesEqual.
   *
   * @since 1.0.0
   *
   * @param ?FacilityCoordinates $previous the coordinates before mutation
   * @param ?FacilityCoordinates $current the coordinates after mutation
   *
   * @return bool true when both are null or carry the same latitude/longitude
   */
  private function coordinatesEqual(?FacilityCoordinates $previous, ?FacilityCoordinates $current): bool
  {
    if (null === $previous && null === $current) {
      return true;
    }

    if (null === $previous || null === $current) {
      return false;
    }

    return $previous->latitude() === $current->latitude() && $previous->longitude() === $current->longitude();
  }

  /**
   * Method isDuplicateCodeConstraintViolation.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the transactional exception
   *
   * @return bool true when the failure is caused by code uniqueness
   */
  private function isDuplicateCodeConstraintViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'uniq_facility_organization_code') || (str_contains($message, 'facilities') && str_contains($message, 'code'))) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
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
