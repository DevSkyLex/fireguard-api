<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\CreateFacility;

use Doctrine\DBAL\Exception\{
  ForeignKeyConstraintViolationException,
  UniqueConstraintViolationException
};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\{
  FacilityArchivedException,
  FacilityCodeAlreadyExistsException,
  FacilityHierarchyException,
  FacilityNotFoundException
};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{
  FacilityId,
  FacilityName,
  FacilityOrganizationId,
  FacilityType
};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use ValueError;

use function str_contains;
use function strtolower;

/**
 * UseCase CreateFacilityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateFacilityHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private UuidFactory $uuidFactory,
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
   * @param CreateFacilityCommand $command the command payload
   *
   * @return CreateFacilityResult the use case result
   */
  public function __invoke(CreateFacilityCommand $command): CreateFacilityResult
  {
    try {
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
      $parentId = $this->resolveParentId($command->parentFacilityId);

      if (null !== $parentId) {
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

      /** @var FacilityId $facilityId */
      $facilityId = $this->uuidFactory->create(FacilityId::class);

      $facility = Facility::create(
        id: $facilityId,
        organizationId: $organizationId,
        type: FacilityType::from($command->type),
        name: new FacilityName($command->name),
        parentFacilityId: $parentId,
        code: $command->code,
        address: $command->address,
        metadata: $command->metadata,
      );
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

      if ($this->isParentConstraintViolation($exception)) {
        throw FacilityNotFoundException::withId((string) ($parentId ?? 'unknown'));
      }

      throw $exception;
    }

    return new CreateFacilityResult(
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
