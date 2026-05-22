<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\RestoreFacility;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\{FacilityArchivedException, FacilityNotFoundException};
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function str_contains;
use function strtolower;

final readonly class RestoreFacilityHandler implements CommandHandler
{
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
  ) {
  }

  public function __invoke(RestoreFacilityCommand $command): RestoreFacilityResult
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
        throw new InvalidArgumentException('Organization not found.');
      }

      throw $exception;
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
}
