<?php

declare(strict_types=1);

namespace Import\Application\Service;

use Facility\Application\Contract\Provisioning\ProvisionFacilityRequest;
use Import\Domain\Exception\ImportRowValidationException;

use function is_numeric;
use function sprintf;
use function trim;

/**
 * Service FacilityRowFactory.
 *
 * Builds a {@see ProvisionFacilityRequest} from one associative CSV data
 * row. Expected header: `type` (required), `name` (required), `code`,
 * `address`, `latitude`, `longitude`, `parentCode`. Unknown columns are
 * ignored. Only structural validation happens here (required columns
 * present, `latitude`/`longitude` are numeric, provided together, and
 * within their valid decimal-degree range, `[-90, 90]`/`[-180, 180]` — a
 * self-contained duplicate of `Facility\Domain\ValueObject\FacilityCoordinates`'s
 * own check, not an import of it, so a **dry-run** row can fail fast on a
 * bad coordinate without ever dispatching `CreateFacilityCommand`);
 * `type` enum membership and the `parentCode` lookup are left to
 * `CreateFacilityHandler` / `FacilityProvisioningService` (via
 * `FacilityProvisioningPort`), which already report an invalid value as a
 * non-fatal `INVALID` outcome — this factory never depends on Facility's
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
final readonly class FacilityRowFactory
{
  // #region Constants
  /**
   * Constant LATITUDE_RANGE.
   *
   * @since 1.0.0
   *
   * @var array{float, float} LATITUDE_RANGE
   */
  private const array LATITUDE_RANGE = [-90.0, 90.0];

  /**
   * Constant LONGITUDE_RANGE.
   *
   * @since 1.0.0
   *
   * @var array{float, float} LONGITUDE_RANGE
   */
  private const array LONGITUDE_RANGE = [-180.0, 180.0];
  // #endregion

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
   *                                      or `latitude`/`longitude` are invalid
   *
   * @return ProvisionFacilityRequest the mapped provisioning request
   */
  public function map(string $organizationId, array $row): ProvisionFacilityRequest
  {
    $type = $this->requiredString($row, 'type');
    $name = $this->requiredString($row, 'name');
    $latitude = $this->optionalFloat($row, 'latitude', ...self::LATITUDE_RANGE);
    $longitude = $this->optionalFloat($row, 'longitude', ...self::LONGITUDE_RANGE);

    if ((null === $latitude) !== (null === $longitude)) {
      throw ImportRowValidationException::invalidColumn(
        null === $latitude ? 'latitude' : 'longitude',
        'latitude and longitude must be provided together.',
      );
    }

    return new ProvisionFacilityRequest(
      organizationId: $organizationId,
      type: $type,
      name: $name,
      code: $this->optionalString($row, 'code'),
      address: $this->optionalString($row, 'address'),
      latitude: $latitude,
      longitude: $longitude,
      parentCode: $this->optionalString($row, 'parentCode'),
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

  /**
   * Method optionalFloat.
   *
   * @since 1.0.0
   *
   * @param array<string, string> $row the associative CSV data row
   * @param string $column the optional numeric column name
   * @param float $min the inclusive lower bound
   * @param float $max the inclusive upper bound
   *
   * @throws ImportRowValidationException when the value is not numeric or out of range
   *
   * @return ?float the parsed value, or null when blank/absent
   */
  private function optionalFloat(array $row, string $column, float $min, float $max): ?float
  {
    $raw = $this->optionalString($row, $column);
    if (null === $raw) {
      return null;
    }

    if (!is_numeric($raw)) {
      throw ImportRowValidationException::invalidColumn($column, 'must be a number.');
    }

    $value = (float) $raw;
    if ($value < $min || $value > $max) {
      throw ImportRowValidationException::invalidColumn(
        $column,
        sprintf('must be between %s and %s degrees.', $min, $max),
      );
    }

    return $value;
  }
  // #endregion
}
