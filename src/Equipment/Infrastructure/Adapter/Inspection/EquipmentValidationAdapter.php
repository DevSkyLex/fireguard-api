<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Inspection;

use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\ValueObject\EquipmentId;
use Inspection\Application\Port\Outbound\EquipmentValidationPort;
use InvalidArgumentException;

use function sprintf;

/**
 * Adapter EquipmentValidationAdapter.
 *
 * Implements the Inspection module's equipment validation port
 * using the Equipment module's repository.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentValidationAdapter implements EquipmentValidationPort
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assertEquipmentExists(string $equipmentId, string $organizationId): void
  {
    $equipment = $this->equipmentRepository->findById(EquipmentId::fromString($equipmentId));

    if (null === $equipment || (string) $equipment->organizationId() !== $organizationId) {
      throw new InvalidArgumentException(sprintf('Equipment with ID "%s" not found.', $equipmentId));
    }
  }
  // #endregion
}
