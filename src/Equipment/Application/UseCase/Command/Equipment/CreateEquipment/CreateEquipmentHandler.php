<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\CreateEquipment;

use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
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
   */
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private UuidFactory $uuidFactory,
    private OrganizationQuotaPort $quota,
    private TransactionManagerPort $transactionManager,
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
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    // Enforce the plan quota and persist atomically: assertCanAdd takes a
    // transaction-scoped advisory lock so concurrent creates at the cap cannot
    // both slip through the count (see OrganizationQuotaPort::assertCanAdd).
    $this->transactionManager->transactional(function () use ($command, $equipment): void {
      $this->quota->assertCanAdd($command->organizationId, OrganizationQuotaResource::EQUIPMENT);
      $this->equipmentRepository->save($equipment);
    });

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
    );
  }

  // #endregion
}
