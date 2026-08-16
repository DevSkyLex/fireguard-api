<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\CreateFacility;

use Doctrine\DBAL\Exception\{
  ForeignKeyConstraintViolationException,
  UniqueConstraintViolationException
};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Event\Facility\FacilityCreatedEvent;
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Domain\Exception\{
  FacilityArchivedException,
  FacilityCodeAlreadyExistsException,
  FacilityHierarchyException,
  FacilityNotFoundException
};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{
  FacilityCoordinates,
  FacilityId,
  FacilityName,
  FacilityOrganizationId,
  FacilityType
};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateFacilityHandler class.
   *
   * @since 1.0.0
   *
   * @param FacilityRepositoryPort $facilityRepository the facility repository value
   * @param UuidFactory $uuidFactory the uuid factory value
   * @param OrganizationQuotaPort $quota the organization quota enforcement port
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param FacilityMetadataSchemaGuard $metadataSchemaGuard the organization's typed metadata schema guard
   * @param int $maxDepth the configured maximum facility hierarchy depth (root = 1)
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private UuidFactory $uuidFactory,
    private OrganizationQuotaPort $quota,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
    private FacilityMetadataSchemaGuard $metadataSchemaGuard,
    #[Autowire('%facility.hierarchy.max_depth%')]
    private int $maxDepth = 8,
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

        // The new facility would sit one level below its parent.
        if ($this->facilityRepository->depthOf($parentId) + 1 > $this->maxDepth) {
          throw FacilityHierarchyException::maxDepthExceeded($this->maxDepth);
        }
      }

      /** @var FacilityId $facilityId */
      $facilityId = null === $command->resourceId
        ? $this->uuidFactory->create(FacilityId::class)
        : FacilityId::fromString($command->resourceId);

      $facility = Facility::create(
        id: $facilityId,
        organizationId: $organizationId,
        type: FacilityType::from($command->type),
        name: new FacilityName($command->name),
        parentFacilityId: $parentId,
        code: $command->code,
        address: $command->address,
        metadata: $command->metadata,
        coordinates: $this->resolveCoordinates($command->latitude, $command->longitude),
      );
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $this->metadataSchemaGuard->assertValid(
      $command->organizationId,
      $facility->metadata(),
      $facility->type()->value,
      true,
    );

    if ($command->dryRun) {
      // A dry run never enters the transaction that would take the quota's
      // advisory lock (see OrganizationQuotaPort::assertCanAdd): it projects
      // the cap instead, offsetting for rows already provisionally counted
      // earlier in the same batch (the Import module's dry-run report).
      $this->quota->assertProjectedCanAdd(
        $command->organizationId,
        OrganizationQuotaResource::FACILITIES,
        $command->quotaProjectionOffset,
      );

      return $this->toResult($facility);
    }

    // Enforce the plan quota and persist in one transaction: assertCanAdd takes a
    // transaction-scoped advisory lock so two concurrent creates at the cap cannot
    // both pass the count and both insert (see OrganizationQuotaPort::assertCanAdd).
    $this->transactionManager->transactional(function () use ($command, $facility, $parentId): void {
      $this->quota->assertCanAdd($command->organizationId, OrganizationQuotaResource::FACILITIES);

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
    });

    // Emitted after the durable save so a failed persistence leaves no
    // ledger row. Both the resource-scoped POST and the canonical PUT
    // upsert route through this same handler, so both emit exactly once.
    $this->eventDispatcher->dispatch(new FacilityCreatedEvent(
      organizationId: (string) $facility->organizationId(),
      facilityId: (string) $facility->id(),
    ));

    return $this->toResult($facility);
  }

  /**
   * Method toResult.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the facility aggregate (persisted, or — for a dry run — validated only)
   *
   * @return CreateFacilityResult the use case result
   */
  private function toResult(Facility $facility): CreateFacilityResult
  {
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
      latitude: $facility->coordinates()?->latitude(),
      longitude: $facility->coordinates()?->longitude(),
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
   * Method resolveCoordinates.
   *
   * Builds the facility coordinates value object. Latitude and longitude
   * must both be provided together, or both omitted.
   *
   * @since 1.0.0
   *
   * @param ?float $latitude the optional latitude
   * @param ?float $longitude the optional longitude
   *
   * @return ?FacilityCoordinates the resolved coordinates
   */
  private function resolveCoordinates(?float $latitude, ?float $longitude): ?FacilityCoordinates
  {
    if (null === $latitude && null === $longitude) {
      return null;
    }

    if (null === $latitude || null === $longitude) {
      throw InvalidValueException::because('Facility latitude and longitude must be provided together.');
    }

    return new FacilityCoordinates($latitude, $longitude);
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
