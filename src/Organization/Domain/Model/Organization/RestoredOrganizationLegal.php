<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use Organization\Domain\ValueObject\{
  OrganizationCountry,
  OrganizationLegalType,
  OrganizationRegistrationNumber,
  OrganizationVatNumber
};

/** Optional legal identity fields persisted with an organization. */
final readonly class RestoredOrganizationLegal
{
  public function __construct(
    public ?OrganizationCountry $country = null,
    public ?OrganizationLegalType $legalType = null,
    public ?string $legalName = null,
    public ?OrganizationRegistrationNumber $registrationNumber = null,
    public ?OrganizationVatNumber $vatNumber = null,
  ) {
  }
}
