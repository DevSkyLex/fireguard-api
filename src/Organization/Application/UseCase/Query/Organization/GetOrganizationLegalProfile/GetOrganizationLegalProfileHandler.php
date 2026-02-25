<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationLegalProfile;

use Organization\Application\Port\Outbound\{
  OrganizationLegalProfileRepositoryPort,
  OrganizationRepositoryPort
};
use Organization\Domain\Exception\{
  OrganizationLegalProfileNotFoundException,
  OrganizationNotFoundException
};
use Organization\Domain\Service\OrganizationLegalRequirementsCatalog;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationLegalProfileHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationLegalProfileHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationLegalProfileRepositoryPort $legalProfileRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns the legal profile for an organization.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationLegalProfileQuery $query the query payload
   *
   * @return GetOrganizationLegalProfileResult the legal profile result
   */
  public function __invoke(GetOrganizationLegalProfileQuery $query): GetOrganizationLegalProfileResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $legalProfile = $this->legalProfileRepository->findByOrganizationId($organizationId);
    if (null === $legalProfile) {
      throw OrganizationLegalProfileNotFoundException::forOrganization($query->organizationId);
    }

    $requirements = OrganizationLegalRequirementsCatalog::resolve($legalProfile->countryCode(), $legalProfile->legalType());

    return new GetOrganizationLegalProfileResult(
      organizationId: (string) $legalProfile->organizationId(),
      countryCode: (string) $legalProfile->countryCode(),
      legalType: $legalProfile->legalType()->value,
      legalName: (string) $legalProfile->legalName(),
      registrationNumber: null !== $legalProfile->registrationNumber() ? (string) $legalProfile->registrationNumber() : null,
      vatNumber: null !== $legalProfile->vatNumber() ? (string) $legalProfile->vatNumber() : null,
      registrationNumberRequired: $requirements['registrationNumber']['required'],
      vatNumberRequired: $requirements['vatNumber']['required'],
      createdAt: $legalProfile->createdAt(),
      updatedAt: $legalProfile->updatedAt(),
    );
  }
  // #endregion
}
