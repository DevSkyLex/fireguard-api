<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\UpdateFacility;

use Doctrine\DBAL\Exception\{
  ForeignKeyConstraintViolationException,
  UniqueConstraintViolationException
};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\{
  FacilityCodeAlreadyExistsException,
  FacilityNotFoundException
};
use Facility\Domain\ValueObject\{
  FacilityCoordinates,
  FacilityId,
  FacilityName,
  FacilityOrganizationId,
  FacilityType
};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
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
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
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
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    try {
      if ($command->hasType) {
        if (null === $command->type) {
          throw new InvalidArgumentException('Field "type" cannot be null when provided.');
        }

        $facility->changeType(FacilityType::from($command->type));
      }

      if ($command->hasName) {
        if (null === $command->name) {
          throw new InvalidArgumentException('Field "name" cannot be null when provided.');
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
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    try {
      $this->facilityRepository->save($facility);
    } catch (Throwable $exception) {
      if ($this->isDuplicateCodeConstraintViolation($exception)) {
        throw FacilityCodeAlreadyExistsException::withCode($facility->code() ?? 'unknown');
      }

      if ($this->isOrganizationConstraintViolation($exception)) {
        throw new InvalidArgumentException('Organization not found.');
      }

      throw $exception;
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
