<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationLegalProfile;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Domain\Service\OrganizationLegalRequirementsCatalog;
use Organization\Domain\ValueObject\{
  OrganizationCountryCode,
  OrganizationId,
  OrganizationLegalName,
  OrganizationLegalType,
  OrganizationRegistrationNumber,
  OrganizationVatNumber
};

use function is_string;
use function preg_match;
use function sprintf;

/**
 * Model OrganizationLegalProfile.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationLegalProfile
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationLegalProfile class.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationCountryCode $countryCode the organization legal country code
   * @param OrganizationLegalType $legalType the legal type
   * @param OrganizationLegalName $legalName the legal name
   * @param ?OrganizationRegistrationNumber $registrationNumber the optional registration number
   * @param ?OrganizationVatNumber $vatNumber the optional VAT number
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  private function __construct(
    private OrganizationId $organizationId,
    private OrganizationCountryCode $countryCode,
    private OrganizationLegalType $legalType,
    private OrganizationLegalName $legalName,
    private ?OrganizationRegistrationNumber $registrationNumber,
    private ?OrganizationVatNumber $vatNumber,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new legal profile aggregate for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationCountryCode $countryCode the organization legal country code
   * @param OrganizationLegalType $legalType the legal type
   * @param OrganizationLegalName $legalName the legal name
   * @param ?OrganizationRegistrationNumber $registrationNumber the optional registration number
   * @param ?OrganizationVatNumber $vatNumber the optional VAT number
   *
   * @return self the created legal profile aggregate
   */
  public static function create(
    OrganizationId $organizationId,
    OrganizationCountryCode $countryCode,
    OrganizationLegalType $legalType,
    OrganizationLegalName $legalName,
    ?OrganizationRegistrationNumber $registrationNumber = null,
    ?OrganizationVatNumber $vatNumber = null,
  ): self {
    self::assertConsistency($countryCode, $legalType, $registrationNumber, $vatNumber);

    $now = new DateTimeImmutable();

    return new self(
      organizationId: $organizationId,
      countryCode: $countryCode,
      legalType: $legalType,
      legalName: $legalName,
      registrationNumber: $registrationNumber,
      vatNumber: $vatNumber,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a legal profile aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationCountryCode $countryCode the organization legal country code
   * @param OrganizationLegalType $legalType the legal type
   * @param OrganizationLegalName $legalName the legal name
   * @param ?OrganizationRegistrationNumber $registrationNumber the optional registration number
   * @param ?OrganizationVatNumber $vatNumber the optional VAT number
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   *
   * @return self the reconstituted legal profile aggregate
   */
  public static function reconstitute(
    OrganizationId $organizationId,
    OrganizationCountryCode $countryCode,
    OrganizationLegalType $legalType,
    OrganizationLegalName $legalName,
    ?OrganizationRegistrationNumber $registrationNumber,
    ?OrganizationVatNumber $vatNumber,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    self::assertConsistency($countryCode, $legalType, $registrationNumber, $vatNumber);

    return new self(
      organizationId: $organizationId,
      countryCode: $countryCode,
      legalType: $legalType,
      legalName: $legalName,
      registrationNumber: $registrationNumber,
      vatNumber: $vatNumber,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method organizationId.
   *
   * Returns the organization identifier.
   *
   * @since 1.0.0
   *
   * @return OrganizationId the organization identifier
   */
  public function organizationId(): OrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method countryCode.
   *
   * Returns the legal country code.
   *
   * @since 1.0.0
   *
   * @return OrganizationCountryCode the legal country code
   */
  public function countryCode(): OrganizationCountryCode
  {
    return $this->countryCode;
  }

  /**
   * Method legalType.
   *
   * Returns the legal type.
   *
   * @since 1.0.0
   *
   * @return OrganizationLegalType the legal type
   */
  public function legalType(): OrganizationLegalType
  {
    return $this->legalType;
  }

  /**
   * Method legalName.
   *
   * Returns the legal name.
   *
   * @since 1.0.0
   *
   * @return OrganizationLegalName the legal name
   */
  public function legalName(): OrganizationLegalName
  {
    return $this->legalName;
  }

  /**
   * Method registrationNumber.
   *
   * Returns the registration number.
   *
   * @since 1.0.0
   *
   * @return ?OrganizationRegistrationNumber the optional registration number
   */
  public function registrationNumber(): ?OrganizationRegistrationNumber
  {
    return $this->registrationNumber;
  }

  /**
   * Method vatNumber.
   *
   * Returns the VAT number.
   *
   * @since 1.0.0
   *
   * @return ?OrganizationVatNumber the optional VAT number
   */
  public function vatNumber(): ?OrganizationVatNumber
  {
    return $this->vatNumber;
  }

  /**
   * Method createdAt.
   *
   * Returns the creation timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * Returns the last update timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the last update timestamp
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method update.
   *
   * Updates mutable legal profile values.
   *
   * @since 1.0.0
   *
   * @param OrganizationCountryCode $countryCode the legal country code
   * @param OrganizationLegalName $legalName the legal name
   * @param ?OrganizationRegistrationNumber $registrationNumber the optional registration number
   * @param ?OrganizationVatNumber $vatNumber the optional VAT number
   */
  public function update(
    OrganizationCountryCode $countryCode,
    OrganizationLegalType $legalType,
    OrganizationLegalName $legalName,
    ?OrganizationRegistrationNumber $registrationNumber,
    ?OrganizationVatNumber $vatNumber,
  ): void {
    self::assertConsistency($countryCode, $legalType, $registrationNumber, $vatNumber);

    $this->countryCode = $countryCode;
    $this->legalType = $legalType;
    $this->legalName = $legalName;
    $this->registrationNumber = $registrationNumber;
    $this->vatNumber = $vatNumber;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method isComplete.
   *
   * Returns whether the legal profile fulfills required fields.
   *
   * @since 1.0.0
   *
   * @return bool true when complete
   */
  public function isComplete(): bool
  {
    $requirements = OrganizationLegalRequirementsCatalog::resolve($this->countryCode, $this->legalType);

    return (!$requirements['registrationNumber']['required'] || null !== $this->registrationNumber)
      && (!$requirements['vatNumber']['required'] || null !== $this->vatNumber);
  }

  /**
   * Method assertConsistency.
   *
   * Validates field requirements based on legal type and country.
   *
   * @since 1.0.0
   *
   * @param OrganizationCountryCode $countryCode the legal country code
   * @param OrganizationLegalType $legalType the legal type
   * @param ?OrganizationRegistrationNumber $registrationNumber the optional registration number
   * @param ?OrganizationVatNumber $vatNumber the optional VAT number
   */
  private static function assertConsistency(
    OrganizationCountryCode $countryCode,
    OrganizationLegalType $legalType,
    ?OrganizationRegistrationNumber $registrationNumber,
    ?OrganizationVatNumber $vatNumber,
  ): void {
    $requirements = OrganizationLegalRequirementsCatalog::resolve($countryCode, $legalType);
    $registrationRule = $requirements['registrationNumber'];
    $vatRule = $requirements['vatNumber'];

    if ($registrationRule['required'] && null === $registrationNumber) {
      throw new InvalidArgumentException(sprintf(
        'Registration number is required for legal type "%s" in country "%s".',
        $legalType->value,
        (string) $countryCode,
      ));
    }

    if ($vatRule['required'] && null === $vatNumber) {
      throw new InvalidArgumentException(sprintf(
        'VAT number is required for legal type "%s" in country "%s".',
        $legalType->value,
        (string) $countryCode,
      ));
    }

    $registrationPattern = $registrationRule['pattern'];
    if (null !== $registrationNumber && is_string($registrationPattern) && '' !== $registrationPattern && !preg_match($registrationPattern, (string) $registrationNumber)) {
      throw new InvalidArgumentException(sprintf(
        'Registration number format is invalid for legal type "%s" in country "%s".',
        $legalType->value,
        (string) $countryCode,
      ));
    }

    $vatPattern = $vatRule['pattern'];
    if (null !== $vatNumber && is_string($vatPattern) && '' !== $vatPattern && !preg_match($vatPattern, (string) $vatNumber)) {
      throw new InvalidArgumentException(sprintf(
        'VAT number format is invalid for legal type "%s" in country "%s".',
        $legalType->value,
        (string) $countryCode,
      ));
    }
  }
  // #endregion
}
