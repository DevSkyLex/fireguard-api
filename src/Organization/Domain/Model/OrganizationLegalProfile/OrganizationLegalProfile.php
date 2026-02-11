<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationLegalProfile;

use DateTimeImmutable;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationLegalName, OrganizationRegistrationNumber, OrganizationVatNumber};

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
   * @param OrganizationLegalName $legalName the legal name
   * @param ?OrganizationRegistrationNumber $registrationNumber the optional registration number
   * @param ?OrganizationVatNumber $vatNumber the optional VAT number
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  private function __construct(
    private OrganizationId $organizationId,
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
   * @param OrganizationLegalName $legalName the legal name
   * @param ?OrganizationRegistrationNumber $registrationNumber the optional registration number
   * @param ?OrganizationVatNumber $vatNumber the optional VAT number
   *
   * @return self the created legal profile aggregate
   */
  public static function create(
    OrganizationId $organizationId,
    OrganizationLegalName $legalName,
    ?OrganizationRegistrationNumber $registrationNumber = null,
    ?OrganizationVatNumber $vatNumber = null,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      organizationId: $organizationId,
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
    OrganizationLegalName $legalName,
    ?OrganizationRegistrationNumber $registrationNumber,
    ?OrganizationVatNumber $vatNumber,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      organizationId: $organizationId,
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
   * @param OrganizationLegalName $legalName the legal name
   * @param ?OrganizationRegistrationNumber $registrationNumber the optional registration number
   * @param ?OrganizationVatNumber $vatNumber the optional VAT number
   */
  public function update(
    OrganizationLegalName $legalName,
    ?OrganizationRegistrationNumber $registrationNumber,
    ?OrganizationVatNumber $vatNumber,
  ): void {
    $this->legalName = $legalName;
    $this->registrationNumber = $registrationNumber;
    $this->vatNumber = $vatNumber;
    $this->updatedAt = new DateTimeImmutable();
  }
  // #endregion
}
