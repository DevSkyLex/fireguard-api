<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use Organization\Domain\ValueObject\{
  OrganizationCountry,
  OrganizationLegalType,
  OrganizationRegisteredAddress,
  OrganizationRegistrationNumber,
  OrganizationVatNumber
};
use Shared\Domain\ValueObject\Email;

/** Optional legal identity fields persisted with an organization. */
final readonly class RestoredOrganizationLegal
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries optional legal identity fields restored for an organization.
   *
   * @access public
   *
   * @param ?OrganizationCountry $country optional country of registration
   * @param ?OrganizationLegalType $legalType optional legal form
   * @param ?string $legalName optional registered legal name
   * @param ?OrganizationRegistrationNumber $registrationNumber optional organization registration number
   * @param ?OrganizationVatNumber $vatNumber optional VAT registration number
   * @param ?OrganizationRegisteredAddress $registeredAddress optional registered office
   * @param ?Email $privacyContactEmail optional organization privacy contact
   *
   * @return void
   */
  public function __construct(
    public ?OrganizationCountry $country = null,
    public ?OrganizationLegalType $legalType = null,
    public ?string $legalName = null,
    public ?OrganizationRegistrationNumber $registrationNumber = null,
    public ?OrganizationVatNumber $vatNumber = null,
    public ?OrganizationRegisteredAddress $registeredAddress = null,
    public ?Email $privacyContactEmail = null,
  ) {
  }
  // #endregion
}
