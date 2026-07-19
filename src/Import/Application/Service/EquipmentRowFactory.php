<?php

declare(strict_types=1);

namespace Import\Application\Service;

use Equipment\Application\Contract\Provisioning\ProvisionEquipmentRequest;
use Import\Domain\Exception\ImportRowValidationException;

use function trim;

/**
 * Service EquipmentRowFactory.
 *
 * Builds a {@see ProvisionEquipmentRequest} from one associative CSV data
 * row. Expected header: `type` (required), `subType`, `brand`, `model`,
 * `serialNumber`, `locationLabel`. Unknown columns are ignored. Only
 * structural validation happens here (required columns present); `type`
 * enum membership is left to `CreateEquipmentHandler` (via
 * `EquipmentProvisioningPort`), which already reports an invalid value as a
 * non-fatal `INVALID` outcome — this factory never depends on Equipment's
 * Domain types.
 *
 * Named "Factory" (not "Mapper"): the "Mapper" suffix is reserved for
 * `Infrastructure/Persistence/` Doctrine record mappers in this codebase
 * (see `tests/Architecture/Unit/ClassLocationTest`), while this class is an
 * Application-layer construction responsibility, mirroring
 * `Shared\Application\Factory\UuidFactory`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentRowFactory
{
  // #region Methods
  /**
   * Method map.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param array<string, string> $row the associative CSV data row
   *
   * @throws ImportRowValidationException when a required column is missing
   *
   * @return ProvisionEquipmentRequest the mapped provisioning request
   */
  public function map(string $organizationId, array $row): ProvisionEquipmentRequest
  {
    return new ProvisionEquipmentRequest(
      organizationId: $organizationId,
      type: $this->requiredString($row, 'type'),
      subType: $this->optionalString($row, 'subType'),
      brand: $this->optionalString($row, 'brand'),
      model: $this->optionalString($row, 'model'),
      serialNumber: $this->optionalString($row, 'serialNumber'),
      locationLabel: $this->optionalString($row, 'locationLabel'),
    );
  }

  /**
   * Method requiredString.
   *
   * @since 1.0.0
   *
   * @param array<string, string> $row the associative CSV data row
   * @param string $column the required column name
   *
   * @throws ImportRowValidationException when the column is missing or blank
   *
   * @return string the trimmed required value
   */
  private function requiredString(array $row, string $column): string
  {
    $value = trim($row[$column] ?? '');
    if ('' === $value) {
      throw ImportRowValidationException::missingRequiredColumn($column);
    }

    return $value;
  }

  /**
   * Method optionalString.
   *
   * @since 1.0.0
   *
   * @param array<string, string> $row the associative CSV data row
   * @param string $column the optional column name
   *
   * @return ?string the trimmed value, or null when blank/absent
   */
  private function optionalString(array $row, string $column): ?string
  {
    $value = trim($row[$column] ?? '');

    return '' === $value ? null : $value;
  }
  // #endregion
}
