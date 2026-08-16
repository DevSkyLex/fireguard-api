<?php

declare(strict_types=1);

namespace Facility\Domain\Model\MetadataField;

use DateTimeImmutable;
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldKey,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId,
  FacilityType
};
use Shared\Domain\Exception\InvalidValueException;

use function array_is_list;
use function array_map;
use function array_unique;
use function count;
use function is_string;
use function mb_strlen;
use function trim;

/**
 * Model FacilityMetadataField.
 *
 * An organization-defined typed attribute field for facility metadata
 * (e.g. "surface m²", "occupancy", "construction year"), optionally scoped
 * to one facility type. This is the form-schema an organization builds for
 * itself; it does not presume any national fire-safety regime.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataField
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataFieldId $id the field identifier
   * @param FacilityOrganizationId $organizationId the owning organization identifier
   * @param FacilityMetadataFieldKey $key the machine key
   * @param FacilityMetadataFieldLabel $label the human-readable label
   * @param FacilityMetadataFieldType $fieldType the field type
   * @param bool $required whether the field is required on facility creation
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param list<string> $options the select options (only meaningful for SELECT)
   * @param ?FacilityType $facilityType the optional facility type scope; null applies to every type
   * @param ?string $unit the optional unit label (e.g. "m²")
   */
  private function __construct(
    private FacilityMetadataFieldId $id,
    private FacilityOrganizationId $organizationId,
    private FacilityMetadataFieldKey $key,
    private FacilityMetadataFieldLabel $label,
    private FacilityMetadataFieldType $fieldType,
    private bool $required,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private array $options = [],
    private ?FacilityType $facilityType = null,
    private ?string $unit = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataFieldId $id the field identifier
   * @param FacilityOrganizationId $organizationId the owning organization identifier
   * @param FacilityMetadataFieldKey $key the machine key
   * @param FacilityMetadataFieldLabel $label the human-readable label
   * @param FacilityMetadataFieldType $fieldType the field type
   * @param bool $required whether the field is required on facility creation
   * @param array<array-key, mixed> $options the select options (only meaningful for SELECT)
   * @param ?FacilityType $facilityType the optional facility type scope
   * @param ?string $unit the optional unit label
   *
   * @return self the created metadata field aggregate
   */
  public static function create(
    FacilityMetadataFieldId $id,
    FacilityOrganizationId $organizationId,
    FacilityMetadataFieldKey $key,
    FacilityMetadataFieldLabel $label,
    FacilityMetadataFieldType $fieldType,
    bool $required = false,
    array $options = [],
    ?FacilityType $facilityType = null,
    ?string $unit = null,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      key: $key,
      label: $label,
      fieldType: $fieldType,
      required: $required,
      createdAt: $now,
      updatedAt: $now,
      options: self::normalizeOptions($fieldType, $options),
      facilityType: $facilityType,
      unit: self::normalizeUnit($unit),
    );
  }

  /**
   * Method reconstitute.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataFieldId $id the field identifier
   * @param FacilityOrganizationId $organizationId the owning organization identifier
   * @param FacilityMetadataFieldKey $key the machine key
   * @param FacilityMetadataFieldLabel $label the human-readable label
   * @param FacilityMetadataFieldType $fieldType the field type
   * @param bool $required whether the field is required on facility creation
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param list<string> $options the select options
   * @param ?FacilityType $facilityType the optional facility type scope
   * @param ?string $unit the optional unit label
   *
   * @return self the reconstituted metadata field aggregate
   */
  public static function reconstitute(
    FacilityMetadataFieldId $id,
    FacilityOrganizationId $organizationId,
    FacilityMetadataFieldKey $key,
    FacilityMetadataFieldLabel $label,
    FacilityMetadataFieldType $fieldType,
    bool $required,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    array $options = [],
    ?FacilityType $facilityType = null,
    ?string $unit = null,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      key: $key,
      label: $label,
      fieldType: $fieldType,
      required: $required,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      options: $options,
      facilityType: $facilityType,
      unit: $unit,
    );
  }

  /**
   * Method rename.
   *
   * @since 1.0.0
   */
  public function rename(FacilityMetadataFieldLabel $label): void
  {
    $this->label = $label;
    $this->touch();
  }

  /**
   * Method changeType.
   *
   * Changing the field type re-validates the current options against the
   * new type (options are dropped unless the new type is SELECT).
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $options the options to keep (only meaningful for SELECT)
   */
  public function changeType(FacilityMetadataFieldType $fieldType, array $options = []): void
  {
    $this->fieldType = $fieldType;
    $this->options = self::normalizeOptions($fieldType, $options);
    $this->touch();
  }

  /**
   * Method changeOptions.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $options the select options
   */
  public function changeOptions(array $options): void
  {
    $this->options = self::normalizeOptions($this->fieldType, $options);
    $this->touch();
  }

  /**
   * Method changeFacilityType.
   *
   * @since 1.0.0
   */
  public function changeFacilityType(?FacilityType $facilityType): void
  {
    $this->facilityType = $facilityType;
    $this->touch();
  }

  /**
   * Method changeRequired.
   *
   * @since 1.0.0
   */
  public function changeRequired(bool $required): void
  {
    $this->required = $required;
    $this->touch();
  }

  /**
   * Method changeUnit.
   *
   * @since 1.0.0
   */
  public function changeUnit(?string $unit): void
  {
    $this->unit = self::normalizeUnit($unit);
    $this->touch();
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): FacilityMetadataFieldId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): FacilityOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method key.
   *
   * @since 1.0.0
   */
  public function key(): FacilityMetadataFieldKey
  {
    return $this->key;
  }

  /**
   * Method label.
   *
   * @since 1.0.0
   */
  public function label(): FacilityMetadataFieldLabel
  {
    return $this->label;
  }

  /**
   * Method fieldType.
   *
   * @since 1.0.0
   */
  public function fieldType(): FacilityMetadataFieldType
  {
    return $this->fieldType;
  }

  /**
   * Method options.
   *
   * @since 1.0.0
   *
   * @return list<string> the select options
   */
  public function options(): array
  {
    return $this->options;
  }

  /**
   * Method facilityType.
   *
   * @since 1.0.0
   */
  public function facilityType(): ?FacilityType
  {
    return $this->facilityType;
  }

  /**
   * Method required.
   *
   * @since 1.0.0
   */
  public function required(): bool
  {
    return $this->required;
  }

  /**
   * Method unit.
   *
   * @since 1.0.0
   */
  public function unit(): ?string
  {
    return $this->unit;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method touch.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method normalizeOptions.
   *
   * SELECT fields require at least two distinct, non-blank options; every
   * other type carries none — options are silently dropped rather than
   * rejected so a type change from SELECT to something else does not force
   * the caller to also clear the payload.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $options the raw options
   *
   * @return list<string> the normalized options
   */
  private static function normalizeOptions(FacilityMetadataFieldType $fieldType, array $options): array
  {
    if (FacilityMetadataFieldType::SELECT !== $fieldType) {
      return [];
    }

    if (!array_is_list($options)) {
      throw InvalidValueException::because('Facility metadata field options must be a list of strings.');
    }

    $normalized = [];
    foreach ($options as $option) {
      if (!is_string($option)) {
        throw InvalidValueException::because('Facility metadata field options must be a list of strings.');
      }

      $trimmedOption = trim($option);
      if ('' === $trimmedOption) {
        throw InvalidValueException::because('Facility metadata field options cannot be blank.');
      }

      if (mb_strlen($trimmedOption) > 80) {
        throw InvalidValueException::because('Facility metadata field options must be at most 80 characters.');
      }

      $normalized[] = $trimmedOption;
    }

    if (count(array_unique($normalized)) !== count($normalized)) {
      throw InvalidValueException::because('Facility metadata field options must be unique.');
    }

    if (count($normalized) < 2) {
      throw InvalidValueException::because('A "select" facility metadata field requires at least two options.');
    }

    return array_map(static fn (string $option): string => $option, $normalized);
  }

  /**
   * Method normalizeUnit.
   *
   * @since 1.0.0
   */
  private static function normalizeUnit(?string $unit): ?string
  {
    if (null === $unit) {
      return null;
    }

    $normalized = trim($unit);
    if ('' === $normalized) {
      return null;
    }

    if (mb_strlen($normalized) > 16) {
      throw InvalidValueException::because('Facility metadata field unit must be at most 16 characters.');
    }

    return $normalized;
  }
  // #endregion
}
