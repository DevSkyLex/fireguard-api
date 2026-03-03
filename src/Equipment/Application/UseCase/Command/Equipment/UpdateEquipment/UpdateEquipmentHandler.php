<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\UpdateEquipment;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentSerialNumberAlreadyExistsException};
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use ValueError;

use function array_map;
use function str_contains;
use function strtolower;

/**
 * UseCase UpdateEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateEquipmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(UpdateEquipmentCommand $command): UpdateEquipmentResult
  {
    try {
      $equipmentId = EquipmentId::fromString($command->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    try {
      $equipment->update(
        type: EquipmentType::from($command->type),
        subType: $command->subType,
        brand: $command->brand,
        model: $command->model,
        serialNumber: $command->serialNumber,
        locationLabel: $command->locationLabel,
      );
    } catch (ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    try {
      $this->equipmentRepository->save($equipment);
    } catch (Throwable $exception) {
      if ($this->isDuplicateSerialNumberViolation($exception)) {
        throw EquipmentSerialNumberAlreadyExistsException::withSerialNumber($command->serialNumber ?? '');
      }

      throw $exception;
    }

    return new UpdateEquipmentResult(
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
      tags: array_map(
        static fn ($tag): array => [
          'id' => (string) $tag->id(),
          'name' => $tag->name(),
          'organizationId' => (string) $tag->organizationId(),
        ],
        $this->tagRepository->findByEquipmentId($equipmentId),
      ),
      createdAt: $equipment->createdAt(),
      updatedAt: $equipment->updatedAt(),
    );
  }

  private function isDuplicateSerialNumberViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'uniq_equipment_organization_serial') || (str_contains($message, 'equipment') && str_contains($message, 'serial'))) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }
  // #endregion
}
