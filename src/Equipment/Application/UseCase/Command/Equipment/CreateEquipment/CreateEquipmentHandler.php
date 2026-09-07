<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\CreateEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use Onboarding\Application\Contract\Setup\OrganizationSetupConflict;
use Onboarding\Application\Port\Inbound\OrganizationSetupPort;
use Organization\Application\Contract\Quota\OrganizationQuotaResource;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * UseCase CreateEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateEquipmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateEquipmentHandler class.
   *
   * @since 1.0.0
   *
   * @param EquipmentRepositoryPort $equipmentRepository the equipment repository value
   * @param UuidFactory $uuidFactory the uuid factory value
   * @param OrganizationQuotaPort $quota the organization quota enforcement port
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param FacilityNamingPort $facilityNaming the facility display-name lookup port
   */
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private UuidFactory $uuidFactory,
    private OrganizationQuotaPort $quota,
    private TransactionManagerPort $transactionManager,
    private FacilityNamingPort $facilityNaming,
    private ?OrganizationSetupPort $setup = null,
    private ?\Equipment\Application\Port\Outbound\FacilityValidationPort $facilityValidation = null,
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
   * @param CreateEquipmentCommand $command the command payload
   *
   * @return CreateEquipmentResult the use case result
   */
  public function __invoke(CreateEquipmentCommand $command): CreateEquipmentResult
  {
    if (null !== $command->setupContext && null === $this->setup) {
      throw OrganizationSetupConflict::because('Setup journaling is unavailable.');
    }

    if (null !== $command->setupContext && ($command->dryRun || null !== $command->resourceId)) {
      throw OrganizationSetupConflict::because('Setup receipts cannot be combined with another creation protocol.');
    }

    try {
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);

      /** @var EquipmentId $equipmentId */
      $equipmentId = null === $command->resourceId
        ? $this->uuidFactory->create(EquipmentId::class)
        : EquipmentId::fromString($command->resourceId);

      $equipment = Equipment::create(
        id: $equipmentId,
        organizationId: $organizationId,
        type: EquipmentType::from($command->type),
        subType: $command->subType,
        brand: $command->brand,
        model: $command->model,
        serialNumber: $command->serialNumber,
        locationLabel: $command->locationLabel,
      );
    } catch (InvalidValueException|ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    if ($command->dryRun) {
      // A dry run never enters the transaction that would take the quota's
      // advisory lock (see OrganizationQuotaPort::assertCanAdd): it projects
      // the cap instead, offsetting for rows already provisionally counted
      // earlier in the same batch (the Import module's dry-run report).
      $this->quota->assertProjectedCanAdd(
        $command->organizationId,
        OrganizationQuotaResource::EQUIPMENT,
        $command->quotaProjectionOffset,
      );

      return $this->toResult($equipment);
    }

    // Enforce the plan quota and persist atomically: assertCanAdd takes a
    // transaction-scoped advisory lock so concurrent creates at the cap cannot
    // both slip through the count (see OrganizationQuotaPort::assertCanAdd).
    $equipment = $this->transactionManager->transactional(function () use ($command, $equipment): Equipment {
      if (null !== $command->setupContext) {
        $operation = ($this->setup ?? throw OrganizationSetupConflict::because('Setup journaling is unavailable.'))->begin($command->setupContext, 'create_first_equipment', $command->organizationId, [
          'type' => $command->type, 'subType' => $command->subType, 'brand' => $command->brand,
          'model' => $command->model, 'serialNumber' => $command->serialNumber, 'locationLabel' => $command->locationLabel,
          'facility' => null !== $command->facilityId ? '/api/facilities/' . $command->facilityId : null,
        ]);
        if (null !== $operation->resourceId) {
          $existing = $this->equipmentRepository->findById(EquipmentId::fromString($operation->resourceId));
          if (null === $existing || (string) $existing->organizationId() !== $command->organizationId) {
            throw OrganizationSetupConflict::because('The created equipment is no longer available.');
          }

          return $existing;
        }
        if (null !== $command->facilityId) {
          if (null === $this->facilityValidation) {
            throw OrganizationSetupConflict::because('Facility validation is unavailable.');
          }
          $this->facilityValidation->assertFacilityIsAssignable($command->facilityId, $command->organizationId);
          $equipment->assignToFacility(\Equipment\Domain\ValueObject\EquipmentFacilityId::fromString($command->facilityId), new DateTimeImmutable());
        }
      }
      $this->quota->assertCanAdd($command->organizationId, OrganizationQuotaResource::EQUIPMENT);
      $this->equipmentRepository->save($equipment);
      if (null !== $command->setupContext) {
        ($this->setup ?? throw OrganizationSetupConflict::because('Setup journaling is unavailable.'))->complete($command->setupContext, 'create_first_equipment', (string) $equipment->id());
      }

      return $equipment;
    });

    return $this->toResult($equipment);
  }

  /**
   * Method toResult.
   *
   * @since 1.0.0
   *
   * @param Equipment $equipment the equipment aggregate (persisted, or — for a dry run — validated only)
   *
   * @return CreateEquipmentResult the use case result
   */
  private function toResult(Equipment $equipment): CreateEquipmentResult
  {
    return new CreateEquipmentResult(
      equipmentId: (string) $equipment->id(),
      organizationId: (string) $equipment->organizationId(),
      facilityId: $equipment->facilityId()?->__toString(),
      type: $equipment->type()->value,
      subType: $equipment->subType(),
      brand: $equipment->brand(),
      model: $equipment->model(),
      serialNumber: $equipment->serialNumber(),
      locationLabel: $equipment->locationLabel(),
      status: $equipment->status()->value,
      installedAt: $equipment->installedAt()?->format('c'),
      commissionedAt: $equipment->commissionedAt()?->format('c'),
      tags: [],
      createdAt: $equipment->createdAt(),
      updatedAt: $equipment->updatedAt(),
      facilityName: $this->resolveFacilityName($equipment),
    );
  }

  /**
   * Method resolveFacilityName.
   *
   * @since 1.0.0
   *
   * @param Equipment $equipment the equipment aggregate
   *
   * @return ?string the assigned facility's display name, or null when unassigned or unresolved
   */
  private function resolveFacilityName(Equipment $equipment): ?string
  {
    $facilityId = $equipment->facilityId()?->__toString();

    if (null === $facilityId) {
      return null;
    }

    return $this->facilityNaming->findNamesByIds([$facilityId])[$facilityId] ?? null;
  }

  // #endregion
}
