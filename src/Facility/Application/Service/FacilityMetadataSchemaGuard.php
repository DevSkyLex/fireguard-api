<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use DateTimeImmutable;
use Exception as PhpException;
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Domain\Exception\FacilityMetadataValidationException;
use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{FacilityMetadataFieldType, FacilityOrganizationId};

use function array_key_exists;
use function array_unique;
use function array_values;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;

/**
 * Service FacilityMetadataSchemaGuard.
 *
 * Validates a facility's `metadata` map against the typed field definitions
 * an organization has created for itself
 * ({@see FacilityMetadataField}). Called
 * from every facility write path: {@see \Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityHandler},
 * {@see \Facility\Application\UseCase\Command\Facility\UpdateFacility\UpdateFacilityHandler},
 * the canonical PATCH processor, and the offline intervention apply() path.
 *
 * Compatibility rule (deliberate, and load-bearing for the existing
 * free-form `metadata` contract): when an organization has **no** field
 * definitions, every payload passes untouched — this feature is additive.
 * When definitions exist, only the metadata keys that match a definition
 * are checked against that definition's type; every other key is passed
 * through unexamined, so the pre-existing free-form usage keeps working
 * side by side with the typed schema. `required` is enforced on **create**
 * only — a partial PATCH is never rejected for omitting a required field it
 * never touched, matching the merge-patch semantics the rest of the module
 * already uses for other properties.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMetadataSchemaGuard
{
  // #region Constructor
  public function __construct(
    private FacilityMetadataFieldRepositoryPort $repository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertValid.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param array<string, mixed> $metadata the facility metadata to validate
   * @param ?string $facilityType the facility's type, used to scope type-specific definitions
   * @param bool $isCreate whether `required` definitions must be enforced
   *
   * @throws FacilityMetadataValidationException when one or more metadata entries fail the schema
   */
  public function assertValid(string $organizationId, array $metadata, ?string $facilityType, bool $isCreate): void
  {
    $organizationIdValue = FacilityOrganizationId::fromString($organizationId);

    $definitions = $this->repository->findByOrganizationId($organizationIdValue);
    if ([] === $definitions) {
      return;
    }

    /** @var array<string, FacilityMetadataField> $applicable */
    $applicable = [];
    foreach ($definitions as $definition) {
      if (null === $definition->facilityType() || $definition->facilityType()->value === $facilityType) {
        $applicable[(string) $definition->key()] = $definition;
      }
    }

    if ([] === $applicable) {
      return;
    }

    $offendingKeys = [];

    foreach ($applicable as $key => $definition) {
      if (!array_key_exists($key, $metadata)) {
        continue;
      }

      $value = $metadata[$key];
      if (null === $value) {
        continue;
      }

      if (!$this->matchesType($definition, $value)) {
        $offendingKeys[] = $key;
      }
    }

    if ($isCreate) {
      foreach ($applicable as $key => $definition) {
        if (!$definition->required()) {
          continue;
        }

        if (!array_key_exists($key, $metadata) || null === $metadata[$key]) {
          $offendingKeys[] = $key;
        }
      }
    }

    if ([] !== $offendingKeys) {
      throw FacilityMetadataValidationException::withOffendingKeys(array_values(array_unique($offendingKeys)));
    }
  }

  /**
   * Method matchesType.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataField $definition the field definition
   * @param mixed $value the candidate value
   *
   * @return bool whether the value parses as the definition's type
   */
  private function matchesType(FacilityMetadataField $definition, mixed $value): bool
  {
    return match ($definition->fieldType()) {
      FacilityMetadataFieldType::TEXT => is_string($value),
      FacilityMetadataFieldType::NUMBER => is_int($value) || is_float($value),
      FacilityMetadataFieldType::BOOLEAN => is_bool($value),
      FacilityMetadataFieldType::DATE => is_string($value) && $this->isIsoDate($value),
      FacilityMetadataFieldType::SELECT => is_string($value) && in_array($value, $definition->options(), true),
    };
  }

  /**
   * Method isIsoDate.
   *
   * Accepts an ISO 8601 date (`Y-m-d`) or date-time.
   *
   * @since 1.0.0
   *
   * @param string $value the candidate date string
   *
   * @return bool whether the value is a valid ISO 8601 date(-time)
   */
  private function isIsoDate(string $value): bool
  {
    if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})?)?$/', $value)) {
      return false;
    }

    try {
      new DateTimeImmutable($value);

      return true;
    } catch (PhpException) {
      return false;
    }
  }
  // #endregion
}
