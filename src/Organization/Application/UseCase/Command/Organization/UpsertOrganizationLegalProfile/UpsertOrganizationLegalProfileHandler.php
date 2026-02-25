<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpsertOrganizationLegalProfile;

use Organization\Application\Port\Outbound\{
  OrganizationLegalProfileRepositoryPort,
  OrganizationRepositoryPort
};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\OrganizationLegalProfile\OrganizationLegalProfile;
use Organization\Domain\Service\OrganizationLegalRequirementsCatalog;
use Organization\Domain\ValueObject\{
  OrganizationCountryCode,
  OrganizationId,
  OrganizationLegalName,
  OrganizationLegalType,
  OrganizationRegistrationNumber,
  OrganizationVatNumber
};
use Shared\Application\Message\CommandHandler;

use function is_string;
use function trim;

/**
 * UseCase UpsertOrganizationLegalProfileHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpsertOrganizationLegalProfileHandler implements CommandHandler
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
   * Creates or updates an organization legal profile.
   *
   * @since 1.0.0
   *
   * @param UpsertOrganizationLegalProfileCommand $command the command payload
   *
   * @return UpsertOrganizationLegalProfileResult the updated legal profile
   */
  public function __invoke(UpsertOrganizationLegalProfileCommand $command): UpsertOrganizationLegalProfileResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $countryCode = OrganizationCountryCode::fromNullable($command->countryCode);
    $legalType = OrganizationLegalType::from($command->legalType);
    $legalName = new OrganizationLegalName($command->legalName);
    $registrationNumber = $this->normalizeNullableString($command->registrationNumber);
    $vatNumber = $this->normalizeNullableString($command->vatNumber);

    $registrationNumberVo = null !== $registrationNumber ? new OrganizationRegistrationNumber($registrationNumber) : null;
    $vatNumberVo = null !== $vatNumber ? new OrganizationVatNumber($vatNumber) : null;

    $legalProfile = $this->legalProfileRepository->findByOrganizationId($organizationId);
    if (!$legalProfile instanceof OrganizationLegalProfile) {
      $legalProfile = OrganizationLegalProfile::create(
        organizationId: $organizationId,
        countryCode: $countryCode,
        legalType: $legalType,
        legalName: $legalName,
        registrationNumber: $registrationNumberVo,
        vatNumber: $vatNumberVo,
      );
    } else {
      $legalProfile->update(
        countryCode: $countryCode,
        legalType: $legalType,
        legalName: $legalName,
        registrationNumber: $registrationNumberVo,
        vatNumber: $vatNumberVo,
      );
    }

    $this->legalProfileRepository->save($legalProfile);
    $requirements = OrganizationLegalRequirementsCatalog::resolve($legalProfile->countryCode(), $legalProfile->legalType());

    return new UpsertOrganizationLegalProfileResult(
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

  /**
   * Method normalizeNullableString.
   *
   * Trims an optional string and converts empty values to null.
   *
   * @since 1.0.0
   *
   * @param ?string $value the raw string value
   *
   * @return ?string the normalized value or null
   */
  private function normalizeNullableString(?string $value): ?string
  {
    if (!is_string($value)) {
      return null;
    }

    $normalized = trim($value);

    return '' === $normalized ? null : $normalized;
  }
  // #endregion
}
